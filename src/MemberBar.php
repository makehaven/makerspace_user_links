<?php

declare(strict_types=1);

namespace Drupal\makerspace_user_links;

use Drupal\Core\Extension\ThemeExtensionList;
use Drupal\Core\File\FileUrlGeneratorInterface;
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
 * The bar renders in the member (Gin) theme only. The public site already has
 * its own header, navigation and search, so there a logged-in member gets just
 * a gold "Member Resources" button in the header's red top strip
 * (buildPublicReturn()) instead of a second bar and a second search box
 * (JR 2026-10-13).
 *
 * Its links come from three menus so staff can reorder or disable them
 * without code:
 *
 * - member-nav: optional extra links beside Member Resources (empty since the
 *   Make / Learn / Connect / Grow dropdowns left the bar; the Member
 *   Resources page lists those journeys in full).
 * - your-dashboards: one entry per role hub, shown as a "Dashboards" menu.
 * - member-account: the buttons beside the viewer's name (switch back,
 *   log out). The name itself opens My Membership.
 *
 * Every tree is access-filtered, so a link the viewer cannot reach never
 * renders. Links may also declare `options.mh_access_permission` for
 * destinations that gate access internally (CiviCRM), or
 * `options.mh_access_callback` for a static visibility check.
 */
class MemberBar implements TrustedCallbackInterface {

  use StringTranslationTrait;

  /**
   * Themes the bar renders in. The kiosk theme and Claro are excluded.
   */
  public const THEMES = ['makerspace_gin'];

  /**
   * The public theme, where members get only the Member Resources button.
   */
  public const PUBLIC_THEME = 'barrio_boostrap_5_makehaven_d11';

  /**
   * The Member Resources page (the member hub).
   */
  public const HUB_NID = 619;

  public function __construct(
    protected MenuLinkTreeInterface $menuTree,
    protected AccountInterface $currentUser,
    protected CurrentPathStack $currentPath,
    protected ThemeExtensionList $themeList,
    protected FileUrlGeneratorInterface $fileUrlGenerator,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks(): array {
    return ['build', 'buildPublicReturn'];
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
   * Returns a placeholder for the public header's Member Resources button.
   */
  public function publicReturnPlaceholder(): array {
    return [
      '#lazy_builder' => ['makerspace_user_links.member_bar:buildPublicReturn', []],
      '#create_placeholder' => TRUE,
      '#weight' => -100,
    ];
  }

  /**
   * Lazy builder: the public theme's way back to the member side.
   *
   * One gold button, plus "Switch back" while staff masquerade (the bar that
   * used to carry it no longer renders on public pages).
   */
  public function buildPublicReturn(): array {
    $cache = [
      'contexts' => ['user.permissions', 'session.is_masquerading'],
      'tags' => ['config:system.menu.member-account'],
    ];
    if ($this->currentUser->isAnonymous() || $this->currentUser->hasPermission('access navigation')) {
      return ['#cache' => $cache];
    }
    $switch_back = array_values(array_filter(
      $this->links('member-account'),
      static fn(array $link): bool => str_contains($link['url'], 'unmasquerade'),
    ));
    return [
      '#theme' => 'makerspace_user_links_member_return',
      '#home_url' => Url::fromRoute('entity.node.canonical', ['node' => self::HUB_NID])->toString(),
      '#account' => $switch_back,
      '#attached' => ['library' => ['makerspace_user_links/member_return']],
      '#cache' => $cache,
    ];
  }

  /**
   * Lazy builder: renders the bar for the current user.
   */
  public function build(): array {
    $cache = [
      'contexts' => ['user', 'url.path', 'session.is_masquerading'],
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

    $hub_url = Url::fromRoute('entity.node.canonical', ['node' => self::HUB_NID])->toString();

    return [
      '#theme' => 'makerspace_user_links_member_bar',
      '#home_url' => $hub_url,
      '#home_active' => $current === '/node/' . self::HUB_NID,
      '#site_url' => Url::fromRoute('<front>')->toString(),
      '#logo_url' => $this->logoUrl(),
      // The bar's search box submits to the Member Resources index, whose
      // type-to-filter box (makerspace_member_navigator) reads ?q.
      '#search_url' => $hub_url,
      '#primary' => $this->links('member-nav', $current, FALSE),
      '#dashboards' => $this->links('your-dashboards', $current, FALSE),
      '#account' => $this->links('member-account', $current, FALSE),
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
   * Returns the URL of the public theme's MakeHaven wordmark.
   *
   * The bar shows it centred as a reminder of whose site this is. It is the
   * file the public header uses (theme logo setting), falling back to the
   * copy shipped with the public theme.
   */
  protected function logoUrl(): string {
    $logo = theme_get_setting('logo.url', self::PUBLIC_THEME);
    if (is_string($logo) && $logo !== '') {
      return $logo;
    }
    $path = $this->themeList->getPath(self::PUBLIC_THEME) . '/logo.png';
    return $this->fileUrlGenerator->generateString($path);
  }

  /**
   * Returns a menu's links that the current user can reach.
   *
   * With $recurse, two levels deep: a parent that points nowhere
   * (`<nolink>`) and has no reachable children is dropped. The bar itself
   * renders top-level links only (no dropdowns besides Dashboards).
   *
   * @return array
   *   A list of links, each keyed title, description, url (empty for
   *   <nolink>), icon, active and children (the same shape, one level).
   */
  public function links(string $menu_name, string $current_path = '', bool $recurse = TRUE): array {
    $parameters = (new MenuTreeParameters())->setMaxDepth($recurse ? 2 : 1)->onlyEnabledLinks();
    $tree = $this->menuTree->load($menu_name, $parameters);
    $tree = $this->menuTree->transform($tree, [
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
    ]);
    return $this->buildLinks($tree, $current_path, $recurse);
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
      // A link may name a static visibility check, e.g. the Business
      // dashboard is offered only to members whose goal is selling or a
      // business (GrowAccess).
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
