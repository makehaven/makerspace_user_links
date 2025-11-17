<?php

declare(strict_types=1);

namespace Drupal\makerspace_user_links\Service;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Drupal\user\UserInterface;

/**
 * Collects and groups admin/staff links for member profiles.
 */
class UserLinkManager {

  use StringTranslationTrait;

  /**
   * Module handler.
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * UserLinkManager constructor.
   */
  public function __construct(ModuleHandlerInterface $module_handler, TranslationInterface $translation) {
    $this->moduleHandler = $module_handler;
    $this->stringTranslation = $translation;
  }

  /**
   * Returns grouped links for the supplied account and viewer.
   */
  public function getGroupedLinks(UserInterface $account, AccountInterface $viewer): array {
    $links = $this->collectLinks($account, $viewer);
    return $this->groupLinks($links);
  }

  /**
   * Invokes hook implementations and normalizes definitions.
   */
  protected function collectLinks(UserInterface $account, AccountInterface $viewer): array {
    $link_sets = $this->moduleHandler->invokeAll('makerspace_user_links_links', [$account, $viewer]);
    $links = [];
    $seen = [];

    foreach ($link_sets as $set) {
      if (!is_array($set)) {
        continue;
      }
      foreach ($set as $definition) {
        if (!is_array($definition)) {
          continue;
        }
        $normalized = $this->normalizeLinkDefinition($definition, $viewer);
        if (!$normalized) {
          continue;
        }
        $id = $normalized['id'] ?? NULL;
        if ($id && isset($seen[$id])) {
          continue;
        }
        if ($id) {
          $seen[$id] = TRUE;
        }
        $links[] = $normalized;
      }
    }

    $this->moduleHandler->alter('makerspace_user_links_links', $links, $account, $viewer);
    return $links;
  }

  /**
   * Groups and orders the collected links.
   */
  protected function groupLinks(array $links): array {
    usort($links, static function (array $a, array $b): int {
      return $a['weight'] <=> $b['weight'] ?: strcasecmp((string) $a['title'], (string) $b['title']);
    });

    $grouped = [];
    foreach ($links as $link) {
      $category = $link['category'] ?? t('Admin Links');
      $category_label = (string) $category;
      $group_key = md5($category_label);

      if (!isset($grouped[$group_key])) {
        $grouped[$group_key] = [
          'label' => $category,
          'links' => [],
          'weight' => $link['group_weight'] ?? 0,
        ];
      }
      $grouped[$group_key]['links'][] = $link;
    }

    usort($grouped, static function (array $a, array $b): int {
      $comparison = ($a['weight'] ?? 0) <=> ($b['weight'] ?? 0);
      if ($comparison !== 0) {
        return $comparison;
      }
      return strcasecmp((string) $a['label'], (string) $b['label']);
    });

    return $grouped;
  }

  /**
   * Normalizes a single link definition.
   */
  protected function normalizeLinkDefinition(array $definition, AccountInterface $viewer): ?array {
    if (isset($definition['access']) && !$definition['access']) {
      return NULL;
    }

    if (!empty($definition['permissions'])) {
      foreach ((array) $definition['permissions'] as $permission) {
        if (!$viewer->hasPermission($permission)) {
          return NULL;
        }
      }
    }

    if (empty($definition['title'])) {
      return NULL;
    }

    $url = $definition['url'] ?? NULL;
    if (!$url && !empty($definition['route_name'])) {
      $url = Url::fromRoute(
        $definition['route_name'],
        $definition['route_parameters'] ?? [],
        $definition['route_options'] ?? []
      );
    }
    elseif (!$url && !empty($definition['uri'])) {
      $url = Url::fromUri($definition['uri'], $definition['url_options'] ?? []);
    }

    if (is_string($url)) {
      $url = Url::fromUri($url);
    }

    if (!$url instanceof Url) {
      return NULL;
    }

    if (!empty($definition['attributes'])) {
      $options = $url->getOptions();
      $options['attributes'] = ($options['attributes'] ?? []) + $definition['attributes'];
      $url->setOptions($options);
    }

    return [
      'id' => $definition['id'] ?? NULL,
      'title' => $definition['title'],
      'url' => $url,
      'description' => $definition['description'] ?? NULL,
      'category' => $definition['category'] ?? NULL,
      'weight' => (int) ($definition['weight'] ?? 0),
      'group_weight' => (int) ($definition['group_weight'] ?? 0),
    ];
  }

}
