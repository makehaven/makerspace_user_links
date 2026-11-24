<?php

declare(strict_types=1);

namespace Drupal\makerspace_user_links\AssignedActivities;

use Drupal\Component\Utility\Html;

/**
 * The one-click actions on /assigned-to-me, and the text they write.
 *
 * Kate (2026-11-24): changing a status or adding a note took five page loads
 * through CiviCRM's view page and full edit form. Each action here is one
 * CiviCRM update. Pure functions, so they can be unit tested without CiviCRM.
 */
final class TaskActions {

  /**
   * Days a snooze moves the due date.
   */
  public const SNOOZE_DAYS = 3;

  /**
   * The button actions.
   *
   * Each has a label, a CiviCRM status name (NULL = unchanged), and whether
   * the task leaves the open list.
   *
   * "Waiting on them" uses CiviCRM's Left Message status, which is still open,
   * so the task stays listed while the other person replies.
   */
  public static function actions(): array {
    return [
      'done' => ['label' => 'Done', 'status' => 'Completed', 'closes' => TRUE],
      'waiting' => ['label' => 'Waiting on them', 'status' => 'Left Message', 'closes' => FALSE],
      'not_needed' => ['label' => 'Not needed', 'status' => 'Not Required', 'closes' => TRUE],
      'snooze' => ['label' => 'Snooze ' . self::SNOOZE_DAYS . ' days', 'status' => NULL, 'closes' => FALSE],
      'note' => ['label' => 'Save note', 'status' => NULL, 'closes' => FALSE],
    ];
  }

  /**
   * Appends a dated, signed note to an activity's HTML details.
   *
   * The note is plain text from staff; it is escaped, never trusted as HTML.
   */
  public static function appendNote(string $details, string $note, string $actor, int $time): string {
    $note = trim($note);
    if ($note === '') {
      return $details;
    }
    $stamp = date('M j, Y g:ia', $time) . ($actor !== '' ? ' · ' . $actor : '');
    $line = '<p><strong>' . Html::escape($stamp) . ':</strong> ' . nl2br(Html::escape($note), FALSE) . '</p>';
    return $details === '' ? $line : rtrim($details) . "\n" . $line;
  }

  /**
   * A note recording a one-click status change, so the history shows it.
   */
  public static function statusNote(string $action): string {
    $label = self::actions()[$action]['label'] ?? '';
    return $label !== '' && in_array($action, ['done', 'waiting', 'not_needed', 'snooze'], TRUE) ? 'Marked "' . $label . '"' : '';
  }

  /**
   * One history line: the status change, the note, or both.
   */
  public static function historyLine(string $action, string $note): string {
    $status = self::statusNote($action);
    $note = trim($note);
    if ($status !== '' && $note !== '') {
      return $status . ' — ' . $note;
    }
    return $status !== '' ? $status : $note;
  }

  /**
   * How a CiviCRM status reads on the card; Scheduled is the default, so ''.
   */
  public static function statusLabel(string $label): string {
    return match ($label) {
      'Scheduled' => '',
      'Left Message' => 'Waiting on them',
      default => $label,
    };
  }

  /**
   * The new due date for a snooze: N days from now at 9am.
   */
  public static function snoozeDate(int $now): string {
    return date('Y-m-d 09:00:00', strtotime('+' . self::SNOOZE_DAYS . ' days', $now));
  }

  /**
   * Mailing address lines from CiviCRM address fields; empty when no street.
   *
   * Kate (2026-11-24): letter tasks (donor thank-yous) should show the address
   * so it can be copied onto an envelope without opening the contact.
   *
   * @param array $address
   *   Keys street_address, supplemental_address_1, supplemental_address_2,
   *   city, state, postal_code.
   *
   * @return string[]
   *   Lines, e.g. ['12 Main St', 'Apt 2', 'New Haven, CT 06510'].
   */
  public static function addressLines(array $address): array {
    $get = static fn(string $key): string => trim((string) ($address[$key] ?? ''));
    if ($get('street_address') === '') {
      return [];
    }
    $lines = array_values(array_filter([
      $get('street_address'),
      $get('supplemental_address_1'),
      $get('supplemental_address_2'),
    ], 'strlen'));
    $cityState = implode(', ', array_filter([$get('city'), $get('state')], 'strlen'));
    $last = trim($cityState . ' ' . $get('postal_code'));
    if ($last !== '') {
      $lines[] = $last;
    }
    return $lines;
  }

  /**
   * A mailto: link that replies about the task, or '' without an email.
   */
  public static function mailto(string $email, string $subject): string {
    $email = trim($email);
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
      return '';
    }
    // A validated address has no characters that need encoding here.
    return 'mailto:' . $email . '?subject=' . rawurlencode($subject !== '' ? 'Re: ' . $subject : 'Following up from MakeHaven');
  }

}
