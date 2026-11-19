<?php

declare(strict_types=1);

namespace Drupal\Tests\makerspace_user_links\Unit;

use Drupal\makerspace_user_links\EventSubscriber\SocialLoginDestination;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * @coversDefaultClass \Drupal\makerspace_user_links\EventSubscriber\SocialLoginDestination
 * @group makerspace_user_links
 */
class SocialLoginDestinationTest extends UnitTestCase {

  /**
   * The Google callback gets back the destination saved before leaving.
   */
  public function testCallbackGetsSavedDestination(): void {
    $request = $this->callbackRequest('google', '/tool-chat/7255');
    $this->dispatch($request);
    $this->assertSame('/tool-chat/7255', $request->query->get('destination'));
  }

  /**
   * Other routes and an empty session are left alone.
   */
  public function testLeavesOtherRequestsAlone(): void {
    $request = $this->callbackRequest('google', NULL);
    $this->dispatch($request);
    $this->assertNull($request->query->get('destination'));

    $other = $this->callbackRequest('google', '/tool-chat/7255');
    $other->attributes->set('_route', 'user.login');
    $this->dispatch($other);
    $this->assertNull($other->query->get('destination'));
  }

  /**
   * @covers ::destinationToRestore
   * @dataProvider cases
   */
  public function testDestinationToRestore(mixed $current, mixed $saved, ?string $expected): void {
    $this->assertSame($expected, SocialLoginDestination::destinationToRestore($current, $saved));
  }

  /**
   * Current and saved destinations, and what gets restored.
   */
  public static function cases(): array {
    return [
      'saved local path' => [NULL, '/tool-chat/7255', '/tool-chat/7255'],
      'request already has one' => ['/events', '/tool-chat/7255', NULL],
      'nothing saved' => [NULL, NULL, NULL],
      'external url' => [NULL, 'https://evil.example/x', NULL],
      'protocol-relative' => [NULL, '//evil.example/x', NULL],
      'not a path' => [NULL, 'javascript:alert(1)', NULL],
    ];
  }

  /**
   * A callback request with Social Auth's saved destination in the session.
   */
  protected function callbackRequest(string $network, ?string $saved): Request {
    $request = Request::create('/user/login/' . $network . '/callback?code=x&state=y');
    $request->attributes->set('_route', SocialLoginDestination::CALLBACK_ROUTE);
    $request->attributes->set('network', $network);
    $session = new Session(new MockArraySessionStorage());
    if ($saved !== NULL) {
      $session->set('social_auth_' . $network . '_login_destination', $saved);
    }
    $request->setSession($session);
    return $request;
  }

  /**
   * Runs the subscriber on a main request.
   */
  protected function dispatch(Request $request): void {
    $kernel = $this->createMock(HttpKernelInterface::class);
    (new SocialLoginDestination())->onRequest(new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST));
  }

}
