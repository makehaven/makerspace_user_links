<?php

declare(strict_types=1);

namespace Drupal\makerspace_user_links\AssignedActivities;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;

/**
 * Emails staff when an open CiviCRM activity is assigned to them.
 *
 * CiviCRM's own "Notify Activity Assignees" email is sent only by its
 * activity forms, so tasks our modules create through the API (giving
 * follow-ups, funder referrals) arrived silently. This covers every path:
 * hook_civicrm_post reports each new assignee row (CiviCRM writes only the
 * added rows on an edit, so a reassignment counts and a re-save does not),
 * and one email per person goes out when the request ends. Sending at
 * shutdown rather than at the hook means the activity is fully written, a
 * batch of tasks becomes one email, and anyone CiviCRM's form already emailed
 * in this request (its case_activity workflow) is skipped.
 *
 * CiviCRM's settings stay the switch: nothing is sent when "Notify Activity
 * Assignees" is off or the activity type is in "Do not notify assignees for".
 */
class AssignmentNotifier {

  /**
   * Activity ids awaiting notice, keyed by assignee contact id.
   *
   * @var array<int, array<int, true>>
   */
  protected array $pending = [];

  /**
   * Contacts CiviCRM's activity form already emailed this request.
   *
   * @var array<int, true>
   */
  protected array $civicrmSent = [];

  /**
   * Whether the shutdown send is registered.
   */
  protected bool $registered = FALSE;

  public function __construct(
    protected AssignedActivities $activities,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected MailManagerInterface $mailManager,
    protected AccountInterface $currentUser,
    protected LoggerChannelInterface $logger,
  ) {}

  /**
   * Records that a contact was just assigned an activity.
   */
  public function assigned(int $activityId, int $contactId): void {
    if ($activityId <= 0 || $contactId <= 0) {
      return;
    }
    $this->pending[$contactId][$activityId] = TRUE;
    if (!$this->registered) {
      $this->registered = TRUE;
      // A shutdown function, not kernel.terminate: CiviCRM ends its form
      // posts with exit().
      drupal_register_shutdown_function([$this, 'send']);
    }
  }

  /**
   * Records that CiviCRM sent its own assignee copy to a contact.
   */
  public function civicrmSent(int $contactId): void {
    $this->civicrmSent[$contactId] = TRUE;
  }

  /**
   * Sends the pending notices.
   *
   * @return int
   *   Emails sent.
   */
  public function send(): int {
    $pending = $this->pending;
    $this->pending = [];
    $this->registered = FALSE;
    if (!$pending || !$this->activities->available()) {
      return 0;
    }
    $settings = \Civi::settings();
    if (!$settings->get('activity_assignee_notification')) {
      return 0;
    }
    $quietTypes = array_map('intval', (array) $settings->get('do_not_notify_assignees_for'));
    $actor = $this->activities->contactId($this->currentUser);

    $sent = 0;
    foreach ($pending as $contactId => $ids) {
      // Assigning yourself needs no email; CiviCRM's form already sent one.
      if ($contactId === $actor || isset($this->civicrmSent[$contactId])) {
        continue;
      }
      try {
        $sent += (int) $this->notify($contactId, array_keys($ids), $quietTypes);
      }
      catch (\Throwable $e) {
        $this->logger->error('Assigned-activity notice to contact @cid failed: @m', [
          '@cid' => $contactId,
          '@m' => $e->getMessage(),
        ]);
      }
    }
    return $sent;
  }

  /**
   * Emails one assignee about the open activities among $ids.
   */
  protected function notify(int $contactId, array $ids, array $quietTypes): bool {
    // Staff only: an active site account that can open the link. Members
    // assigned something by mistake are not emailed internal records.
    $uid = (int) \CRM_Core_BAO_UFMatch::getUFId($contactId);
    $account = $uid ? $this->entityTypeManager->getStorage('user')->load($uid) : NULL;
    if (!$account || !$account->isActive() || !$account->getEmail() || !$account->hasPermission('access CiviCRM')) {
      return FALSE;
    }
    $rows = array_values(array_filter(
      $this->activities->open($contactId, $ids, 50),
      fn(array $row) => !in_array($row['type_id'], $quietTypes, TRUE),
    ));
    if (!$rows) {
      return FALSE;
    }
    $actorName = $this->currentUser->isAuthenticated() ? $this->currentUser->getDisplayName() : '';
    $result = $this->mailManager->mail('makerspace_user_links', 'activity_assigned', $account->getEmail(), $account->getPreferredLangcode(), [
      'rows' => $rows,
      'actor' => $actorName,
      'list_url' => Url::fromRoute('makerspace_user_links.assigned_to_me', [], ['absolute' => TRUE])->toString(),
    ]);
    if (!empty($result['result'])) {
      $this->logger->info('Emailed @mail about @n assigned CiviCRM activit(ies): @ids', [
        '@mail' => $account->getEmail(),
        '@n' => count($rows),
        '@ids' => implode(', ', array_column($rows, 'id')),
      ]);
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Builds the email for hook_mail().
   */
  public static function message(array &$message, array $params): void {
    $rows = $params['rows'];
    $by = $params['actor'] !== '' ? $params['actor'] : 'The website';
    $first = $rows[0];
    $message['subject'] = count($rows) === 1
      ? 'Assigned to you: ' . self::title($first)
      : count($rows) . ' CiviCRM items assigned to you';

    $lines = [];
    $lines[] = count($rows) === 1
      ? $by . ' assigned you this in CiviCRM:'
      : $by . ' assigned you these in CiviCRM:';
    foreach ($rows as $row) {
      $lines[] = '';
      $lines[] = self::title($row);
      if ($row['targets']) {
        $lines[] = 'With: ' . implode(', ', array_slice($row['targets'], 0, 5)) . (count($row['targets']) > 5 ? ' and ' . (count($row['targets']) - 5) . ' more' : '');
      }
      if ($row['date'] !== '') {
        $lines[] = 'Due: ' . date('D, M j, Y g:ia', strtotime($row['date']));
      }
      if (count($rows) === 1 && ($excerpt = self::excerpt($row['details'])) !== '') {
        $lines[] = '';
        $lines[] = $excerpt;
      }
      $lines[] = 'Open it: ' . $row['url'];
    }
    $lines[] = '';
    $lines[] = 'Everything open and assigned to you: ' . $params['list_url'];
    $lines[] = 'It stays on that list until you mark it Completed in CiviCRM.';
    $message['body'][] = implode("\n", $lines);
  }

  /**
   * Formats "Type: subject", or whichever of the two exists.
   */
  protected static function title(array $row): string {
    return implode(': ', array_filter([$row['type'], $row['subject']], 'strlen')) ?: 'Activity #' . $row['id'];
  }

  /**
   * The first few hundred characters of the details, as plain text.
   */
  protected static function excerpt(string $details): string {
    $text = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags(str_replace(['</p>', '<br>', '<br />'], ' ', $details)), ENT_QUOTES)));
    return mb_strlen($text) > 400 ? rtrim(mb_substr($text, 0, 400)) . '…' : $text;
  }

}
