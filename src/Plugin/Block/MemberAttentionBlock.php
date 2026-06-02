<?php

declare(strict_types=1);

namespace Drupal\makerspace_user_links\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * "Needs your attention" strip for the current member.
 *
 * Consolidates per-member action items into one ranked strip instead of
 * scattering banners across the page. Modules contribute items by implementing
 * hook_makerspace_member_attention() (see makerspace_user_links.api.php) — the
 * owning module knows its own data (lending overdue, unsigned waiver, etc.), so
 * this block stays decoupled. Renders nothing when there is nothing to show.
 *
 * Note: membership/billing state and badge next-steps are already surfaced by
 * the chargebee membership banner and appointment_facilitator's badge helper,
 * so contributors should avoid duplicating those.
 */
#[\Drupal\Core\Block\Attribute\Block(
  id: 'makerspace_member_attention',
  admin_label: new \Drupal\Core\StringTranslation\TranslatableMarkup('Member: needs attention'),
  category: new \Drupal\Core\StringTranslation\TranslatableMarkup('Makerspace'),
)]
class MemberAttentionBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Severity ranking (lower sorts first / most urgent).
   */
  protected const SEVERITY_RANK = [
    'critical' => 0,
    'warning' => 1,
    'info' => 2,
  ];

  /**
   * The module handler.
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * The current user.
   */
  protected AccountInterface $currentUser;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ModuleHandlerInterface $module_handler, AccountInterface $current_user) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->moduleHandler = $module_handler;
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
      $container->get('module_handler'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    if ($this->currentUser->isAnonymous()) {
      return [];
    }

    // Collect items from every implementing module.
    $items = [];
    $cache_tags = [];
    foreach ($this->moduleHandler->invokeAll('makerspace_member_attention', [$this->currentUser]) as $item) {
      if (!is_array($item) || empty($item['message'])) {
        continue;
      }
      $item += [
        'id' => NULL,
        'severity' => 'info',
        'weight' => 0,
        'action_label' => NULL,
        'action_url' => NULL,
        'icon' => 'exclamation-circle',
      ];
      if (!empty($item['cache_tags'])) {
        $cache_tags = array_merge($cache_tags, (array) $item['cache_tags']);
      }
      unset($item['cache_tags']);
      $items[] = $item;
    }

    if (!$items) {
      return [];
    }

    // Most urgent first, then by weight, then stable by message.
    usort($items, function (array $a, array $b): int {
      $sa = self::SEVERITY_RANK[$a['severity']] ?? 1;
      $sb = self::SEVERITY_RANK[$b['severity']] ?? 1;
      return $sa <=> $sb ?: ($a['weight'] <=> $b['weight']) ?: strcasecmp((string) $a['message'], (string) $b['message']);
    });

    return [
      '#theme' => 'makerspace_user_links_member_attention',
      '#items' => $items,
      '#attached' => ['library' => ['makerspace_user_links/member_attention']],
      '#cache' => [
        // Output is per-user and changes as the underlying data changes.
        'contexts' => ['user'],
        'tags' => array_values(array_unique($cache_tags)),
        // Short max-age so e.g. a just-paid tab clears promptly even if a
        // contributor forgot to supply a cache tag.
        'max-age' => 300,
      ],
    ];
  }

}
