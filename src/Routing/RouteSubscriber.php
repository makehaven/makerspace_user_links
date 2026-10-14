<?php

declare(strict_types=1);

namespace Drupal\makerspace_user_links\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Titles the member profile page with the member's name.
 *
 * The profile module titles /user/{uid}/main with the profile type label, so
 * staff (and the member) saw only "Member" with no name anywhere on the page
 * (Kate, 2026-05 and 2026-06).
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection): void {
    if ($route = $collection->get('profile.user_page.single')) {
      $route->setDefault('_title_callback', '\Drupal\makerspace_user_links\Controller\ProfileTitleController::title');
    }
  }

}
