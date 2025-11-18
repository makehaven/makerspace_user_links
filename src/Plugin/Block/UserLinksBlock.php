<?php

declare(strict_types=1);

namespace Drupal\makerspace_user_links\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\makerspace_user_links\Service\UserLinkManager;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a block that lists makerspace admin/staff links.
 *
 * @Block(
 *   id = "makerspace_user_links_block",
 *   admin_label = @Translation("Makerspace user links"),
 *   category = @Translation("Makerspace")
 * )
 */
class UserLinksBlock extends BlockBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * Link manager service.
   */
  protected UserLinkManager $linkManager;

  /**
   * Current user.
   */
  protected AccountInterface $currentUser;

  /**
   * Route match service.
   */
  protected RouteMatchInterface $routeMatch;

  /**
   * UserLinksBlock constructor.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, UserLinkManager $link_manager, AccountInterface $current_user, RouteMatchInterface $route_match) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->linkManager = $link_manager;
    $this->currentUser = $current_user;
    $this->routeMatch = $route_match;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('makerspace_user_links.link_manager'),
      $container->get('current_user'),
      $container->get('current_route_match')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $account = $this->getRoutedUser();
    if (!$account) {
      return [
        '#markup' => $this->t('No user selected.'),
      ];
    }

    $groups = $this->linkManager->getGroupedLinks($account, $this->currentUser);
    return [
      '#theme' => 'makerspace_user_links_list',
      '#link_groups' => $groups,
      '#attached' => [
        'library' => ['makerspace_user_links/user_links'],
      ],
      '#cache' => [
        'contexts' => ['route', 'url.path', 'user.permissions'],
        'tags' => $account->getCacheTags(),
      ],
    ];
  }

  /**
   * Gets the user entity referenced by the current route.
   */
  protected function getRoutedUser(): ?UserInterface {
    $route_user = $this->routeMatch->getParameter('user');
    if ($route_user instanceof UserInterface) {
      return $route_user;
    }
    if (is_numeric($route_user)) {
      return \Drupal::entityTypeManager()->getStorage('user')->load((int) $route_user);
    }
    return NULL;
  }

}
