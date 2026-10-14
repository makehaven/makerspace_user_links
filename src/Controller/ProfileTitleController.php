<?php

declare(strict_types=1);

namespace Drupal\makerspace_user_links\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\user\UserInterface;

/**
 * Title callback for the single-profile page (see RouteSubscriber).
 */
class ProfileTitleController extends ControllerBase {

  /**
   * Titles the page "Member profile: Jane Doe" (other types "Type: name").
   *
   * @param \Drupal\user\UserInterface $user
   *   The member whose profile this is.
   * @param \Drupal\profile\Entity\ProfileTypeInterface $profile_type
   *   The profile type.
   */
  public function title(UserInterface $user, $profile_type): string {
    $label = $profile_type->getDisplayLabel() ?: $profile_type->label();
    if ($profile_type->id() === 'main') {
      $label = $this->t('Member profile');
    }
    return (string) $this->t('@label: @name', [
      '@label' => $label,
      '@name' => $user->getDisplayName(),
    ]);
  }

}
