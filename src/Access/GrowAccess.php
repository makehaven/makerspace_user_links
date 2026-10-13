<?php

declare(strict_types=1);

namespace Drupal\makerspace_user_links\Access;

use Drupal\Core\Session\AccountInterface;

/**
 * Who is OFFERED the Grow journey (selling, business) and its dashboard.
 *
 * Members choose a "Goal at MakeHaven" on the join form (profile field
 * field_member_goal). Grow is for those who picked "Produce products/art to
 * sell" or "Business entrepreneurship", for business-role holders, and for
 * staff. Everyone else would find it empty of anything they asked for. The
 * pages themselves are open to any member; this only decides who is shown
 * them (the bar's and Member Resources' Business dashboard entry).
 */
class GrowAccess {

  /**
   * Goal values that make Grow relevant.
   */
  public const GOALS = ['seller', 'entrepreneur'];

  /**
   * Whether to offer Grow to this account.
   *
   * Referenced from menu links' `mh_access_callback` option (MemberBar,
   * RoleMenuCardsBlock).
   */
  public static function visible(AccountInterface $account): bool {
    return in_array('business', $account->getRoles(), TRUE)
      || $account->hasPermission('access navigation')
      || self::hasGrowGoal((int) $account->id());
  }

  /**
   * Whether the member's main profile names a selling or business goal.
   */
  public static function hasGrowGoal(int $uid): bool {
    $storage = \Drupal::entityTypeManager()->getStorage('profile');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('uid', $uid)
      ->condition('type', 'main')
      ->condition('field_member_goal', self::GOALS, 'IN')
      ->range(0, 1)
      ->execute();
    return (bool) $ids;
  }

}
