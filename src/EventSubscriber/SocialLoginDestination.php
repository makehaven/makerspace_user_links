<?php

declare(strict_types=1);

namespace Drupal\makerspace_user_links\EventSubscriber;

use Drupal\Component\Utility\UrlHelper;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Brings people back where they started after "Continue with Google".
 *
 * Social Auth saves the ?destination of the Google link in the session and
 * takes it off the request before leaving for Google, so the callback request
 * arrives without one. Login Destination's "redirect_all_to_resources" rule
 * then sees no destination, sets /node/619 on the request, and Drupal's
 * redirect subscriber prefers that over Social Auth's own redirect. Every
 * Google login landed on Member Resources (JR 11-19: Ask on the large format
 * printer station, Google login, never got back to /tool-chat). Putting the
 * saved destination back on the callback request lets Login Destination's
 * preserve_destination keep it.
 */
class SocialLoginDestination implements EventSubscriberInterface {

  const CALLBACK_ROUTE = 'social_auth.network.callback';

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // After the router (32), before the controller logs the user in.
    return [KernelEvents::REQUEST => ['onRequest', 30]];
  }

  /**
   * Copies Social Auth's saved destination onto the callback request.
   */
  public function onRequest(RequestEvent $event): void {
    if (!$event->isMainRequest()) {
      return;
    }
    $request = $event->getRequest();
    if ($request->attributes->get('_route') !== self::CALLBACK_ROUTE || !$request->hasSession()) {
      return;
    }
    $network = $request->attributes->get('network');
    $plugin_id = is_object($network) && method_exists($network, 'getPluginId')
      ? (string) $network->getPluginId()
      : 'social_auth_' . (string) $request->attributes->get('network');
    $saved = $request->getSession()->get($plugin_id . '_login_destination');
    $destination = static::destinationToRestore($request->query->get('destination'), $saved);
    if ($destination !== NULL) {
      $request->query->set('destination', $destination);
    }
  }

  /**
   * The destination to put back, or NULL to leave the request alone.
   *
   * Only a local path is restored, and never over one already on the request.
   */
  public static function destinationToRestore(mixed $current, mixed $saved): ?string {
    if (is_string($current) && $current !== '') {
      return NULL;
    }
    if (!is_string($saved) || $saved === '' || UrlHelper::isExternal($saved) || !str_starts_with($saved, '/') || str_starts_with($saved, '//')) {
      return NULL;
    }
    return $saved;
  }

}
