<?php

declare(strict_types=1);

namespace Drupal\makerspace_user_links\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\profile\Entity\ProfileInterface;
use Drupal\user\UserInterface;

/**
 * Who may open a member's profile page (/user/{uid}, /profile/{id} for main).
 *
 * The member profile form offers "Public" and "Members Only", but nothing
 * read the choice: anonymous visitors hold 'access user profiles' and 'view
 * any main profile', so every account page was public — including the 551 of
 * 877 current members who picked Members Only, and every former member
 * (2026-10-22). The rule:
 *
 * - the person and staff who administer users always see it;
 * - a current member (member role) set to Public is open to everyone;
 * - anything else (Members Only, no choice made, or not a current member) is
 *   open to viewers with 'view members only profiles' (the member role).
 *   Former members' pages stay with staff only.
 *
 * Gated at the route, not with entity access: user entity 'view' access also
 * decides whether a name renders at all in entity reference fields (event
 * instructors, authors), and hiding names was never the intent.
 */
class ProfileVisibility {

  /**
   * The member role whose holders count as current members.
   */
  public const MEMBER_ROLE = 'member';

  /**
   * Route access for entity.user.canonical.
   */
  public static function routeAccess(UserInterface $user, AccountInterface $account): AccessResultInterface {
    return self::access($user, $account);
  }

  /**
   * Whether $viewer may see $person's profile page, with cacheability.
   */
  public static function access(UserInterface $person, AccountInterface $viewer): AccessResultInterface {
    $profile = self::mainProfile($person);
    $result = AccessResult::allowedIf(self::canView($person, $viewer, $profile))
      ->cachePerPermissions()
      ->cachePerUser()
      ->addCacheableDependency($person);
    if ($profile) {
      $result->addCacheableDependency($profile);
    }
    else {
      $result->addCacheTags(['profile_list']);
    }
    return $result;
  }

  /**
   * The decision itself (see the class comment).
   */
  public static function canView(UserInterface $person, AccountInterface $viewer, ?ProfileInterface $profile = NULL): bool {
    if ((int) $viewer->id() === (int) $person->id() || $viewer->hasPermission('administer users')) {
      return TRUE;
    }
    $current = $person->hasRole(self::MEMBER_ROLE);
    if (!$current) {
      return FALSE;
    }
    if (self::isPublic($profile ?? self::mainProfile($person))) {
      return TRUE;
    }
    return $viewer->hasPermission('view members only profiles');
  }

  /**
   * Whether a main profile chose "Public". No choice counts as Members Only.
   */
  public static function isPublic(?ProfileInterface $profile): bool {
    if (!$profile || !$profile->hasField('field_member_profile_privacy')) {
      return FALSE;
    }
    $term = $profile->get('field_member_profile_privacy')->entity;
    return $term && strtolower(trim((string) $term->label())) === 'public';
  }

  /**
   * The person's default main profile, if any.
   */
  public static function mainProfile(UserInterface $person): ?ProfileInterface {
    if (!\Drupal::moduleHandler()->moduleExists('profile') || $person->isAnonymous()) {
      return NULL;
    }
    /** @var \Drupal\profile\ProfileStorageInterface $storage */
    $storage = \Drupal::entityTypeManager()->getStorage('profile');
    return $storage->loadByUser($person, 'main') ?: NULL;
  }

}
