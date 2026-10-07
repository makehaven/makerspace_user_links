<?php

declare(strict_types=1);

namespace Drupal\makerspace_user_links\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Member Resources: every member link, searchable, with its description.
 *
 * Built from the same menus as the journey pages and My Membership, so the
 * index cannot drift from them (it used to be a separate menu,
 * menu-member-menu, and had lost 22 links by 2026-10-06). Each menu's
 * top-level <nolink> items become sections; their children are the links.
 * The markup matches what makerspace_member_navigator's type-to-filter box
 * expects (ul.menu > li > span heading + ul.menu > li), and the filter
 * attaches to this plugin by id.
 */
#[Block(
  id: 'makerspace_member_index',
  admin_label: new TranslatableMarkup('Member Resources index (all journeys)'),
  category: new TranslatableMarkup('Makerspace'),
)]
class MemberIndexBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Menus in page order, with the area name shown on each section.
   */
  public const MENUS = [
    'journey-make' => 'Make',
    'journey-learn' => 'Learn',
    'journey-connect' => 'Connect',
    'journey-grow' => 'Grow',
    'member-membership' => 'My Membership',
  ];

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected MenuLinkTreeInterface $menuTree,
    protected AccountInterface $currentUser,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('menu.link_tree'),
      $container->get('current_user'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $sections = [];
    $tags = [];
    // A destination can sit in two menus (Refer a friend is in Connect and
    // My Membership); the index lists it once, where it first appears.
    $seen = [];
    foreach (self::MENUS as $menu => $area) {
      $tags[] = 'config:system.menu.' . $menu;
      $tree = $this->menuTree->load($menu, (new MenuTreeParameters())->setMaxDepth(2)->onlyEnabledLinks());
      $tree = $this->menuTree->transform($tree, [
        ['callable' => 'menu.default_tree_manipulators:checkAccess'],
        ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
      ]);
      $loose = [];
      foreach ($tree as $element) {
        if (!$element->access || !$element->access->isAllowed()) {
          continue;
        }
        if ($element->subtree) {
          $links = $this->unseen(array_map([$this, 'link'], $element->subtree), $seen);
          if ($links) {
            $sections[] = ['area' => $area, 'title' => $element->link->getTitle(), 'links' => $links];
          }
        }
        else {
          $loose = array_merge($loose, $this->unseen([$this->link($element)], $seen));
        }
      }
      if ($loose) {
        $sections[] = ['area' => $area, 'title' => $area, 'links' => $loose];
      }
    }

    return [
      '#theme' => 'makerspace_user_links_member_index',
      '#sections' => $sections,
      '#attached' => ['library' => ['makerspace_user_links/member_index']],
      '#cache' => [
        'contexts' => ['user.permissions', 'user.roles'],
        'tags' => $tags,
      ],
    ];
  }

  /**
   * Drops NULL entries and destinations already listed.
   */
  protected function unseen(array $links, array &$seen): array {
    $out = [];
    foreach ($links as $link) {
      if ($link && !isset($seen[$link['url']])) {
        $seen[$link['url']] = TRUE;
        $out[] = $link;
      }
    }
    return $out;
  }

  /**
   * One index entry, or NULL when the viewer should not see it.
   */
  protected function link($element): ?array {
    if (!$element->access || !$element->access->isAllowed()) {
      return NULL;
    }
    $link = $element->link;
    $options = $link->getOptions();
    $permission = $options['mh_access_permission'] ?? NULL;
    if ($permission && !$this->currentUser->hasPermission($permission)) {
      return NULL;
    }
    try {
      $url = $link->getUrlObject()->toString();
    }
    catch (\Exception) {
      return NULL;
    }
    return [
      'title' => $link->getTitle(),
      'url' => $url,
      'description' => (string) $link->getDescription(),
      'icon' => preg_replace('/[^a-z0-9-]/', '', (string) ($options['attributes']['data-icon'] ?? 'link-45deg')),
    ];
  }

}
