<?php

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\user\UserInterface;

/**
 * @file
 * API documentation for makerspace_user_links.
 */

/**
 * Provide admin/staff links for a given member profile.
 *
 * @param \Drupal\user\UserInterface $account
 *   The member being viewed.
 * @param \Drupal\Core\Session\AccountInterface $viewer
 *   The current user viewing the page.
 * @param string $audience
 *   The target audience for the links ('admin', 'facilitator', or 'member'). Defaults to 'admin'.
 *
 * @return array[]
 *   A list of link definitions. Each entry may contain:
 *   - id: (string) Optional unique ID used for deduplication.
 *   - title: (string|\Drupal\Core\StringTranslation\TranslatableMarkup) Required label.
 *   - url: (\Drupal\Core\Url) Direct URL object.
 *   - route_name/route_parameters/route_options OR uri/url_options: Alternative
 *     ways to build a URL if 'url' is not supplied.
 *   - description: (string|\Drupal\Component\Render\MarkupInterface) Optional
 *     helper text shown under the link.
 *   - category: (string|\Drupal\Core\StringTranslation\TranslatableMarkup)
 *     Optional category heading.
 *   - audience: (string) Target audience ('admin' or 'member'). Defaults to 'admin'.
 *   - weight: (int) Sort weight within its category.
 *   - group_weight: (int) Sort weight for the category wrapper.
 *   - attributes: (array) HTML attributes added to the link (target, rel, etc).
 *   - permissions: (string[]) Optional list of permissions required to show the
 *     link.
 *   - access: (bool) Explicit TRUE/FALSE flag to override default visibility.
 */
function hook_makerspace_user_links_links(UserInterface $account, AccountInterface $viewer, string $audience = 'admin'): array {
  $links = [];

  if ($audience === 'admin') {
    $links[] = [
      'id' => 'example',
      'title' => t('Example Admin link'),
      'url' => Url::fromRoute('entity.user.canonical', ['user' => $account->id()]),
      'description' => t('Describe what this action does.'),
      'category' => t('Demo tools'),
      'weight' => -10,
      'attributes' => ['target' => '_blank'],
    ];
  }

  return $links;
}

/**
 * Alter hook for makerspace_user_links_links().
 */
function hook_makerspace_user_links_links_alter(array &$links, UserInterface $account, AccountInterface $viewer): void {
  // Example: remove a link by ID.
  $links = array_values(array_filter($links, static fn(array $link) => ($link['id'] ?? '') !== 'example'));
}

/**
 * Contribute "needs your attention" items for a member.
 *
 * The MemberAttentionBlock (placed on /resources) collects items from every
 * implementing module, ranks them by severity then weight, and renders one
 * consolidated strip. Implement this in the module that OWNS the data (e.g.
 * lending_library for overdue loans) so the block stays decoupled. Return an
 * empty array when there is nothing to surface — the strip hides itself if no
 * module returns anything.
 *
 * Do NOT duplicate signals already surfaced on /resources: membership/billing
 * state (chargebee membership banner) and badge next-steps (the
 * appointment_facilitator first-badge helper) are already shown.
 *
 * @param \Drupal\Core\Session\AccountInterface $account
 *   The current member.
 *
 * @return array[]
 *   A list of item definitions. Each entry may contain:
 *   - id: (string) Optional unique ID.
 *   - severity: (string) 'critical', 'warning' (default), or 'info'. Controls
 *     ordering and color.
 *   - message: (string|\Drupal\Component\Render\MarkupInterface) Required. The
 *     short action sentence shown to the member.
 *   - action_label: (string) Optional CTA text.
 *   - action_url: (\Drupal\Core\Url|string) Optional CTA target.
 *   - icon: (string) Optional Bootstrap Icons name (default exclamation-circle).
 *   - weight: (int) Optional tiebreak within a severity (lower first).
 *   - cache_tags: (string[]) Optional cache tags so the strip invalidates when
 *     the underlying state changes.
 */
function hook_makerspace_member_attention(AccountInterface $account): array {
  $items = [];

  // Example: surface an overdue lending item.
  if (/* $account has an overdue loan */ FALSE) {
    $items[] = [
      'id' => 'lending_overdue',
      'severity' => 'critical',
      'message' => t('You have an overdue tool. Please return or renew it.'),
      'action_label' => t('View loans'),
      'action_url' => \Drupal\Core\Url::fromUserInput('/user/' . $account->id() . '/loans'),
      'icon' => 'clock-history',
      'cache_tags' => ['user:' . $account->id()],
    ];
  }

  return $items;
}
