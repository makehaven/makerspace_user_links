<?php

declare(strict_types=1);

namespace Drupal\makerspace_user_links;

use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;

/**
 * Builds the logged-in member bar shown at the top of every member page.
 *
 * The bar replaces core's Navigation sidebar for members, facilitators,
 * instructors, borrowers and content editors. Staff roles keep
 * `access navigation` and get the sidebar INSTEAD of the bar, never both.
 *
 * Everything in the bar comes from three menus so staff can reorder or
 * disable links without code:
 *
 * - member-nav: the primary destinations.
 * - your-dashboards: one entry per role hub, shown as a "Dashboards" menu.
 * - member-account: the buttons beside the viewer's name (switch back,
 *   log out). The name itself opens My Membership.
 *
 * Every tree is access-filtered, so a link the viewer cannot reach never
 * renders. Links may also declare `options.mh_access_permission` for
 * destinations that gate access internally (CiviCRM).
 */
class MemberBar implements TrustedCallbackInterface {

  use StringTranslationTrait;

  /**
   * Themes the bar renders in. The kiosk theme and Claro are excluded.
   */
  public const THEMES = ['makerspace_gin', 'barrio_boostrap_5_makehaven_d11'];

  public function __construct(
    protected MenuLinkTreeInterface $menuTree,
    protected AccountInterface $currentUser,
    protected CurrentPathStack $currentPath,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks(): array {
    return ['build'];
  }

  /**
   * Returns a placeholder for the bar; the bar varies per user and page.
   */
  public function placeholder(): array {
    return [
      '#lazy_builder' => ['makerspace_user_links.member_bar:build', []],
      '#create_placeholder' => TRUE,
    ];
  }

  /**
   * Lazy builder: renders the bar for the current user.
   */
  public function build(): array {
    $cache = [
      'contexts' => ['user', 'url.path'],
      'tags' => [
        'config:system.menu.member-nav',
        'config:system.menu.member-account',
        'config:system.menu.your-dashboards',
      ],
    ];
    // Staff keep core's Navigation sidebar and get no bar. Core pins the
    // sidebar and its top bar (page actions such as Edit) to the top of the
    // window, so the two cannot share it, and staff asked for fewer menus.
    if ($this->currentUser->isAnonymous() || $this->currentUser->hasPermission('access navigation')) {
      return ['#cache' => $cache];
    }

    $current = $this->currentPath->getPath();

    return [
      '#theme' => 'makerspace_user_links_member_bar',
      '#home_url' => Url::fromRoute('entity.node.canonical', ['node' => 619])->toString(),
      '#site_url' => Url::fromRoute('<front>')->toString(),
      '#primary' => $this->links('member-nav', $current),
      '#dashboards' => $this->links('your-dashboards', $current),
      '#account' => $this->links('member-account', $current),
      '#display_name' => $this->currentUser->getDisplayName(),
      // The name opens My Membership (node 5737): profile, login, billing,
      // badges, storage. No account dropdown (JR 2026-10-06).
      '#membership_url' => Url::fromRoute('entity.node.canonical', ['node' => 5737])->toString(),
      '#membership_active' => $current === '/node/5737',
      '#attached' => ['library' => ['makerspace_user_links/member_bar']],
      '#cache' => $cache,
    ];
  }

  /**
   * Returns a menu's links that the current user can reach, two levels deep.
   *
   * A top-level link with children renders as a dropdown in the bar (for
   * example Facilitators: Who's On, My appointments). A parent that points
   * nowhere (`<nolink>`) and has no reachable children is dropped.
   *
   * @return array
   *   A list of links, each keyed title, description, url (empty for
   *   <nolink>), icon, active and children (the same shape, one level).
   */
  public function links(string $menu_name, string $current_path = ''): array {
    $parameters = (new MenuTreeParameters())->setMaxDepth(2)->onlyEnabledLinks();
    $tree = $this->menuTree->load($menu_name, $parameters);
    $tree = $this->menuTree->transform($tree, [
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
    ]);
    return $this->buildLinks($tree, $current_path, TRUE);
  }

  /**
   * Turns an access-checked menu tree into template variables.
   */
  protected function buildLinks(array $tree, string $current_path, bool $recurse): array {
    $links = [];
    foreach ($tree as $element) {
      if (!$element->access || !$element->access->isAllowed()) {
        continue;
      }
      $link = $element->link;
      $options = $link->getOptions();
      $permission = $options['mh_access_permission'] ?? NULL;
      if ($permission && !$this->currentUser->hasPermission($permission)) {
        continue;
      }
      // A link may name a static visibility check, e.g. Grow is offered only
      // to members whose goal is selling or a business.
      $callback = $options['mh_access_callback'] ?? NULL;
      if ($callback && is_callable($callback) && !$callback($this->currentUser)) {
        continue;
      }
      $url = $link->getUrlObject();
      $nolink = $url->isRouted() && $url->getRouteName() === '<nolink>';
      try {
        $href = $nolink ? '' : $url->toString();
        $internal = (!$nolink && $url->isRouted()) ? '/' . $url->getInternalPath() : '';
      }
      catch (\Exception) {
        // A link whose providing module is off; skip it rather than break
        // every page.
        continue;
      }
      $children = ($recurse && $element->subtree) ? $this->buildLinks($element->subtree, $current_path, FALSE) : [];
      if ($nolink && !$children) {
        continue;
      }
      $active = $internal !== '' && $internal === $current_path;
      foreach ($children as $child) {
        $active = $active || $child['active'];
      }
      $links[] = [
        'title' => (string) $link->getTitle(),
        'description' => (string) $link->getDescription(),
        'url' => $href,
        'icon' => preg_replace('/[^a-z0-9-]/', '', (string) ($options['attributes']['data-icon'] ?? '')),
        'active' => $active,
        'children' => $children,
      ];
    }
    return $links;
  }

}
