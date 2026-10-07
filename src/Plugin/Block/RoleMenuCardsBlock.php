<?php

declare(strict_types=1);

namespace Drupal\makerspace_user_links\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Renders a menu's top-level items as a row of icon cards.
 *
 * A reusable, configurable alternative to the legacy `member_menu_icons`
 * asset injector (which hardcodes block-ID prefixes and per-href icon rules).
 * Point an instance at any menu via block config and gate it with the normal
 * block visibility (role + path); the menu tree is access-filtered, so cards
 * a user cannot reach never render. Icons come from each link's
 * `options.attributes.data-icon` (a Bootstrap Icons name), with a default
 * fallback — so styling lives in code, not in a per-link CSS rule.
 */
#[\Drupal\Core\Block\Attribute\Block(
  id: 'makerspace_role_menu_cards',
  admin_label: new \Drupal\Core\StringTranslation\TranslatableMarkup('Role menu (as cards)'),
  category: new \Drupal\Core\StringTranslation\TranslatableMarkup('Makerspace'),
)]
class RoleMenuCardsBlock extends BlockBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * The menu link tree service.
   */
  protected MenuLinkTreeInterface $menuTree;

  /**
   * The current user.
   */
  protected AccountInterface $currentUser;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MenuLinkTreeInterface $menu_tree, AccountInterface $current_user) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->menuTree = $menu_tree;
    $this->currentUser = $current_user;
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
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'menu' => '',
      'default_icon' => 'grid',
      'groups' => FALSE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state): array {
    $form['menu'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Menu machine name'),
      '#description' => $this->t('The menu to render as cards, e.g. <code>instructor-tools</code> or <code>facilitator-menu</code>.'),
      '#default_value' => $this->configuration['menu'],
      '#required' => TRUE,
    ];
    $form['default_icon'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Default Bootstrap Icons name'),
      '#description' => $this->t('Used for links that do not set their own <code>data-icon</code>. E.g. <code>grid</code>, <code>speedometer2</code>.'),
      '#default_value' => $this->configuration['default_icon'],
    ];
    $form['groups'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show child links as titled groups'),
      '#description' => $this->t('A top-level link with children renders as a heading with its children as cards (e.g. a "Billing" group). Off: only top-level links render.'),
      '#default_value' => $this->configuration['groups'],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state): void {
    $this->configuration['menu'] = $form_state->getValue('menu');
    $this->configuration['default_icon'] = $form_state->getValue('default_icon') ?: 'grid';
    $this->configuration['groups'] = (bool) $form_state->getValue('groups');
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $menu_name = $this->configuration['menu'];
    if (!$menu_name) {
      return [];
    }

    // Access-filtered so unreachable cards drop out. With 'groups' on, a
    // top-level link with children becomes a titled group of cards.
    $groups_on = !empty($this->configuration['groups']);
    $parameters = (new MenuTreeParameters())->setMaxDepth($groups_on ? 2 : 1)->onlyEnabledLinks();
    $tree = $this->menuTree->load($menu_name, $parameters);
    $tree = $this->menuTree->transform($tree, [
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
    ]);

    $cards = [];
    $groups = [];
    foreach ($tree as $element) {
      if ($groups_on && $element->subtree) {
        $children = array_filter(array_map([$this, 'card'], $element->subtree));
        if ($children && ($element->access && $element->access->isAllowed())) {
          $groups[] = ['title' => $element->link->getTitle(), 'cards' => array_values($children)];
        }
        continue;
      }
      if ($card = $this->card($element)) {
        $cards[] = $card;
      }
    }

    if (!$cards && !$groups) {
      return [];
    }

    return [
      '#theme' => 'makerspace_user_links_menu_cards',
      // With only groups, their headings say it; the block title would repeat.
      '#title' => $cards ? $this->label() : '',
      '#cards' => $cards,
      '#groups' => $groups,
      '#attached' => ['library' => ['makerspace_user_links/menu_cards']],
      '#cache' => [
        'contexts' => ['user.permissions', 'user.roles', 'route'],
        'tags' => ['config:system.menu.' . $menu_name],
      ],
    ];
  }

  /**
   * Returns one card for a menu tree element, or NULL if it should not show.
   *
   * Some destinations gate access internally rather than at the Drupal route
   * level (e.g. CiviCRM's /civicrm is `_access: TRUE` and enforces its own
   * permissions), so the menu access-filter cannot hide them. A link may
   * declare `options.mh_access_permission` to require a Drupal permission
   * before its card renders.
   */
  protected function card($element): ?array {
    if (!$element->access || !$element->access->isAllowed()) {
      return NULL;
    }
    $link = $element->link;
    $options = $link->getOptions();
    $required_permission = $options['mh_access_permission'] ?? NULL;
    if ($required_permission && !$this->currentUser->hasPermission($required_permission)) {
      return NULL;
    }
    $icon = $options['attributes']['data-icon'] ?? $this->configuration['default_icon'];
    return [
      'title' => $link->getTitle(),
      'url' => $link->getUrlObject()->toString(),
      'description' => (string) $link->getDescription(),
      'icon' => preg_replace('/[^a-z0-9-]/', '', (string) $icon),
    ];
  }

}
