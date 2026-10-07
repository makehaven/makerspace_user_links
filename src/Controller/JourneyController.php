<?php

declare(strict_types=1);

namespace Drupal\makerspace_user_links\Controller;

use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The member journey pages: Make, Learn, Connect, Grow.
 *
 * Each journey is a topic where a member's data and actions come together
 * (JR 2026-10-06). For now a page is its journey-* menu rendered as grouped
 * cards, reusing the makerspace_role_menu_cards block; personal panels
 * ("where you stand") are placed as ordinary blocks on the page path, so
 * they can be added one at a time. Links live in menus so staff can reorder
 * or disable them without code.
 */
class JourneyController extends ControllerBase {

  public function __construct(protected BlockManagerInterface $blockManager) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('plugin.manager.block'));
  }

  /**
   * Renders one journey page.
   */
  public function page(string $journey): array {
    $block = $this->blockManager->createInstance('makerspace_role_menu_cards', [
      'menu' => 'journey-' . $journey,
      'groups' => TRUE,
      'label' => '',
      'default_icon' => 'grid',
    ]);
    return [
      'intro' => [
        '#markup' => '<p class="mh-journey__intro">' . $this->intro($journey) . '</p>',
      ],
      'cards' => $block->build(),
      'all' => [
        '#type' => 'link',
        '#title' => $this->t('Browse all member resources'),
        '#url' => Url::fromRoute('entity.node.canonical', ['node' => 619]),
        '#prefix' => '<p class="mh-journey__all">',
        '#suffix' => '</p>',
      ],
      '#cache' => [
        'contexts' => ['user.permissions', 'user.roles'],
        'tags' => ['config:system.menu.journey-' . $journey],
      ],
    ];
  }

  /**
   * One line under each page title.
   */
  protected function intro(string $journey): string {
    return (string) match ($journey) {
      'make' => $this->t('Tools, rooms, materials and help in the shop, for whatever you are working on.'),
      'learn' => $this->t('Your badges, checkouts and classes: the way to unlock the next tool.'),
      'connect' => $this->t('Volunteer, meet other members, and bring friends in.'),
      'grow' => $this->t('Sell what you make and build a business at MakeHaven.'),
      default => '',
    };
  }

}
