<?php

declare(strict_types=1);

namespace Drupal\makerspace_user_links\AssignedActivities;

use Civi\Api4\Activity;
use Civi\Api4\ActivityContact;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Open CiviCRM activities assigned to a staff contact.
 *
 * "Open" is CiviCRM's own incomplete status class (Scheduled, Left Message,
 * ...). It is what separates real to-dos from the thousands of records filed
 * under a staff member's name: filed inbound emails and Calendly tours are
 * written already Completed (Kate, 2026-10-22: 4,918 assigned, none open).
 */
class AssignedActivities {

  public function __construct(
    protected ModuleHandlerInterface $moduleHandler,
  ) {}

  /**
   * Whether CiviCRM is installed and booted for this request.
   */
  public function available(): bool {
    if (!$this->moduleHandler->moduleExists('civicrm') || !\Drupal::hasService('civicrm')) {
      return FALSE;
    }
    try {
      \Drupal::service('civicrm')->initialize();
      return TRUE;
    }
    catch (\Throwable $e) {
      return FALSE;
    }
  }

  /**
   * The CiviCRM contact of a Drupal account, or NULL.
   */
  public function contactId(AccountInterface $account): ?int {
    if ($account->isAnonymous() || !$this->available()) {
      return NULL;
    }
    $id = \CRM_Core_BAO_UFMatch::getContactId($account->id());
    return $id ? (int) $id : NULL;
  }

  /**
   * Status values CiviCRM counts as not done.
   *
   * @return int[]
   *   Activity status values.
   */
  public function openStatuses(): array {
    return array_map('intval', array_keys(\CRM_Activity_BAO_Activity::getStatusesByType(\CRM_Activity_BAO_Activity::INCOMPLETE)));
  }

  /**
   * Open activities assigned to a contact, soonest first.
   *
   * @param int $contactId
   *   The assignee.
   * @param int[]|null $ids
   *   Limit to these activities (the notifier's just-assigned set).
   * @param int $limit
   *   Maximum rows.
   *
   * @return array[]
   *   Rows with id, subject, date, type, overdue, targets (names), source
   *   (name), target_id and url.
   */
  public function open(int $contactId, ?array $ids = NULL, int $limit = 200): array {
    $get = Activity::get(FALSE)
      ->addSelect('id', 'subject', 'details', 'activity_date_time', 'activity_type_id', 'activity_type_id:label')
      ->addWhere('assignee_contact_id', 'CONTAINS', $contactId)
      ->addWhere('status_id', 'IN', $this->openStatuses())
      ->addWhere('is_deleted', '=', FALSE)
      ->addWhere('is_current_revision', '=', TRUE)
      ->addOrderBy('activity_date_time', 'ASC')
      ->setLimit($limit);
    if ($ids !== NULL) {
      if (!$ids) {
        return [];
      }
      $get->addWhere('id', 'IN', array_values($ids));
    }
    $activities = (array) $get->execute()->indexBy('id');
    if (!$activities) {
      return [];
    }

    // Who each activity is with, and who filed it.
    $people = [];
    $links = ActivityContact::get(FALSE)
      ->addSelect('activity_id', 'contact_id', 'contact_id.display_name', 'record_type_id:name')
      ->addWhere('activity_id', 'IN', array_keys($activities))
      ->addWhere('record_type_id:name', 'IN', ['Activity Targets', 'Activity Source'])
      ->execute();
    foreach ($links as $link) {
      $role = $link['record_type_id:name'] === 'Activity Source' ? 'source' : 'targets';
      $people[$link['activity_id']][$role][(int) $link['contact_id']] = (string) $link['contact_id.display_name'];
    }

    $now = \Drupal::time()->getRequestTime();
    $rows = [];
    foreach ($activities as $id => $activity) {
      $targets = $people[$id]['targets'] ?? [];
      $source = $people[$id]['source'] ?? [];
      $date = (string) ($activity['activity_date_time'] ?? '');
      $cid = array_key_first($targets) ?? array_key_first($source);
      $rows[] = [
        'id' => (int) $id,
        'subject' => trim((string) ($activity['subject'] ?? '')),
        'details' => (string) ($activity['details'] ?? ''),
        'type' => (string) ($activity['activity_type_id:label'] ?? ''),
        'type_id' => (int) ($activity['activity_type_id'] ?? 0),
        'date' => $date,
        'overdue' => $date !== '' && strtotime($date) < $now,
        'targets' => array_values($targets),
        'source' => (string) (reset($source) ?: ''),
        'url' => $this->url((int) $id, $cid),
      ];
    }
    return $rows;
  }

  /**
   * Open and overdue counts for a contact.
   *
   * @return int[]
   *   ['open' => n, 'overdue' => n].
   */
  public function counts(int $contactId): array {
    $base = fn() => Activity::get(FALSE)
      ->selectRowCount()
      ->addWhere('assignee_contact_id', 'CONTAINS', $contactId)
      ->addWhere('status_id', 'IN', $this->openStatuses())
      ->addWhere('is_deleted', '=', FALSE)
      ->addWhere('is_current_revision', '=', TRUE);
    return [
      'open' => $base()->execute()->countMatched(),
      'overdue' => $base()->addWhere('activity_date_time', '<', date('Y-m-d H:i:s', \Drupal::time()->getRequestTime()))->execute()->countMatched(),
    ];
  }

  /**
   * Absolute link to an activity in CiviCRM.
   */
  public function url(int $activityId, ?int $contactId = NULL): string {
    $query = 'action=view&reset=1&id=' . $activityId . ($contactId ? '&cid=' . $contactId : '');
    return \CRM_Utils_System::url('civicrm/activity', $query, TRUE, NULL, FALSE);
  }

}
