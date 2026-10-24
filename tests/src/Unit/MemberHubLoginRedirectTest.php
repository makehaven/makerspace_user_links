<?php

declare(strict_types=1);

namespace Drupal\Tests\makerspace_user_links\Unit;

use Drupal\makerspace_user_links\EventSubscriber\MemberHubLoginRedirect;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\makerspace_user_links\EventSubscriber\MemberHubLoginRedirect
 * @group makerspace_user_links
 */
class MemberHubLoginRedirectTest extends UnitTestCase {

  /**
   * @covers ::shouldRedirect
   * @dataProvider cases
   */
  public function testShouldRedirect(bool $anonymous, string $route, string $method, string $alias, bool $expected): void {
    $this->assertSame($expected, MemberHubLoginRedirect::shouldRedirect($anonymous, $route, $method, $alias));
  }

  /**
   * Request shapes and whether each goes to the login page.
   */
  public static function cases(): array {
    return [
      'anonymous visitor on the hub' => [TRUE, 'entity.node.canonical', 'GET', '/member/resources', TRUE],
      'anonymous HEAD on the hub' => [TRUE, 'entity.node.canonical', 'HEAD', '/member/resources', TRUE],
      'logged-in member on the hub' => [FALSE, 'entity.node.canonical', 'GET', '/member/resources', FALSE],
      'anonymous on another page' => [TRUE, 'entity.node.canonical', 'GET', '/member-perks', FALSE],
      'neighbouring alias' => [TRUE, 'entity.node.canonical', 'GET', '/member/resources-old', FALSE],
      'hub edit form' => [TRUE, 'entity.node.edit_form', 'GET', '/member/resources', FALSE],
      'anonymous POST' => [TRUE, 'entity.node.canonical', 'POST', '/member/resources', FALSE],
    ];
  }

}
