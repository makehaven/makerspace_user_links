<?php

declare(strict_types=1);

namespace Drupal\makerspace_user_links\EventSubscriber;

use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\path_alias\AliasManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Sends logged-out visitors from Member Resources to the login page.
 *
 * Member Resources (/member/resources) is the members' hub: a card wall
 * built from the member menu plus the member chat. Drupal drops menu links
 * a visitor cannot open, so logged out the page showed only faint section
 * headings and nothing to click (JR, 2026-10-24, opening the What's New
 * link while logged out). Logging in and coming straight back is what that
 * visitor wants.
 */
class MemberHubLoginRedirect implements EventSubscriberInterface {

  use StringTranslationTrait;

  const HUB_ALIAS = '/member/resources';

  public function __construct(
    protected AccountInterface $currentUser,
    protected AliasManagerInterface $aliasManager,
    protected MessengerInterface $messenger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // After the router (32) has resolved the node.
    return [KernelEvents::REQUEST => ['onRequest', 30]];
  }

  /**
   * Redirects an anonymous GET of the hub node to /user/login.
   */
  public function onRequest(RequestEvent $event): void {
    if (!$event->isMainRequest()) {
      return;
    }
    $request = $event->getRequest();
    $node = $request->attributes->get('node');
    if (!static::shouldRedirect(
      $this->currentUser->isAnonymous(),
      (string) $request->attributes->get('_route'),
      $request->getMethod(),
      $node instanceof NodeInterface ? $this->aliasManager->getAliasByPath('/node/' . $node->id()) : '',
    )) {
      return;
    }
    $this->messenger->addStatus($this->t('Member Resources is for members. Log in and you will come straight back here.'));
    $url = Url::fromRoute('user.login', [], ['query' => ['destination' => self::HUB_ALIAS]])->toString();
    $event->setResponse(new RedirectResponse($url));
  }

  /**
   * Whether this request should go to the login page.
   */
  public static function shouldRedirect(bool $anonymous, string $route, string $method, string $alias): bool {
    return $anonymous
      && $route === 'entity.node.canonical'
      && in_array($method, ['GET', 'HEAD'], TRUE)
      && $alias === self::HUB_ALIAS;
  }

}
