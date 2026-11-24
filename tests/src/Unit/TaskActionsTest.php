<?php

declare(strict_types=1);

namespace Drupal\Tests\makerspace_user_links\Unit;

use Drupal\makerspace_user_links\AssignedActivities\TaskActions;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\makerspace_user_links\AssignedActivities\TaskActions
 * @group makerspace_user_links
 */
class TaskActionsTest extends UnitTestCase {

  /**
   * Notes are appended, dated, signed and escaped.
   */
  public function testAppendNote(): void {
    $time = mktime(14, 5, 0, 11, 24, 2026);
    $out = TaskActions::appendNote('<p>Original</p>', "Called <b>back</b>\nleft voicemail", 'Kate Cebik', $time);
    $this->assertStringStartsWith('<p>Original</p>' . "\n", $out);
    $this->assertStringContainsString('Nov 24, 2026 2:05pm · Kate Cebik:', $out);
    $this->assertStringContainsString('Called &lt;b&gt;back&lt;/b&gt;<br>', $out);
    $this->assertStringNotContainsString('<b>back', $out);
    $this->assertSame('<p>Original</p>', TaskActions::appendNote('<p>Original</p>', '   ', 'Kate', $time));
    $this->assertStringStartsWith('<p><strong>', TaskActions::appendNote('', 'First', '', $time));
  }

  /**
   * Status buttons leave a history line; notes and unknown actions do not.
   */
  public function testStatusNote(): void {
    $this->assertSame('Marked "Done"', TaskActions::statusNote('done'));
    $this->assertSame('Marked "Waiting on them"', TaskActions::statusNote('waiting'));
    $this->assertSame('', TaskActions::statusNote('note'));
    $this->assertSame('', TaskActions::statusNote('bogus'));
  }

  /**
   * Status and note share one line; either alone stands alone.
   */
  public function testHistoryLine(): void {
    $this->assertSame('Marked "Waiting on them" — Called, left voicemail', TaskActions::historyLine('waiting', ' Called, left voicemail '));
    $this->assertSame('Marked "Done"', TaskActions::historyLine('done', ''));
    $this->assertSame('Just a note', TaskActions::historyLine('note', 'Just a note'));
    $this->assertSame('', TaskActions::historyLine('note', ''));
  }

  /**
   * CiviCRM's Left Message reads as Waiting on them; Scheduled is unlabelled.
   */
  public function testStatusLabel(): void {
    $this->assertSame('Waiting on them', TaskActions::statusLabel('Left Message'));
    $this->assertSame('', TaskActions::statusLabel('Scheduled'));
    $this->assertSame('Unreachable', TaskActions::statusLabel('Unreachable'));
  }

  /**
   * Only Done and Not needed close a task; statuses are CiviCRM names.
   */
  public function testActions(): void {
    $actions = TaskActions::actions();
    $this->assertSame(['done', 'not_needed'], array_keys(array_filter($actions, static fn($a) => $a['closes'])));
    $this->assertSame('Completed', $actions['done']['status']);
    $this->assertSame('Left Message', $actions['waiting']['status']);
    $this->assertSame('Not Required', $actions['not_needed']['status']);
    $this->assertNull($actions['snooze']['status']);
  }

  /**
   * Only giving follow-up tasks point at a gift.
   */
  public function testGiftId(): void {
    $this->assertSame(5163, TaskActions::giftId('makerspace_donor_thanks', 5163));
    $this->assertSame(77, TaskActions::giftId('makerspace_tribute_notice', 77));
    $this->assertSame(0, TaskActions::giftId('Meeting', 5163));
    $this->assertSame(0, TaskActions::giftId('makerspace_donor_thanks', 0));
  }

  /**
   * Snooze lands three days out at 9am.
   */
  public function testSnoozeDate(): void {
    $this->assertSame('2026-11-27 09:00:00', TaskActions::snoozeDate(mktime(16, 30, 0, 11, 24, 2026)));
  }

  /**
   * Addresses format as envelope lines; no street means no address.
   */
  public function testAddressLines(): void {
    $this->assertSame(
      ['12 Main St', 'Apt 2', 'New Haven, CT 06510'],
      TaskActions::addressLines([
        'street_address' => '12 Main St',
        'supplemental_address_1' => 'Apt 2',
        'supplemental_address_2' => '',
        'city' => 'New Haven',
        'state' => 'CT',
        'postal_code' => '06510',
      ])
    );
    $this->assertSame([], TaskActions::addressLines(['city' => 'New Haven', 'postal_code' => '06510']));
    $this->assertSame(['1 Elm'], TaskActions::addressLines(['street_address' => '1 Elm']));
  }

  /**
   * Reply links need a valid address and carry the subject.
   */
  public function testMailto(): void {
    $this->assertSame('mailto:a@example.org?subject=Re%3A%20Sewing%20class', TaskActions::mailto('a@example.org', 'Sewing class'));
    $this->assertSame('', TaskActions::mailto('not an email', 'x'));
    $this->assertSame('', TaskActions::mailto('', 'x'));
    $this->assertStringContainsString('Following%20up', TaskActions::mailto('a@example.org', ''));
  }

}
