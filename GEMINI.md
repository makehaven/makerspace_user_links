# Makerspace User Links

This is a custom Drupal module designed to collect, group, and display administrative and staff links on user profile pages. It provides a centralized service and block for actions relevant to managing a specific member.

## Project Overview

- **Purpose**: Collects admin/staff links for member profiles (e.g., Masquerade, CiviCRM links, Badge assignment).
- **Type**: Drupal Custom Module (`makerspace_user_links`).
- **Core Requirement**: Drupal ^10 || ^11.
- **Key Features**:
  - Pluggable link collection system via custom hooks.
  - Automatic link normalization (supports routes, URIs, and permission checks).
  - Grouped and weighted categories for a clean UI.
  - Context-aware block that detects the user from the current route.

## Architecture

- **Service (`UserLinkManager`)**: The core logic provider. It invokes hooks, normalizes link definitions, performs access checks (permissions), and groups/sorts links. It supports three target audiences: `member`, `facilitator`, and `admin`.
- **Block Plugins**:
  - `MemberActionsBlock`: Displays links for the member viewing their own profile (e.g., Edit Profile, My Tab).
  - `FacilitatorActionsBlock`: Displays links for facilitators acting on member profiles (e.g., Add Badge).
  - `AdminUserLinksBlock`: Displays deep administrative links (e.g., Masquerade, CiviCRM, Stripe).
- **Hooks**:
  - `hook_makerspace_user_links_links(UserInterface $account, AccountInterface $viewer, string $audience)`: Allows modules to contribute links for a specific user and audience.
  - `hook_makerspace_user_links_links_alter(array &$links, UserInterface $account, AccountInterface $viewer)`: Allows modifying the collected links.
- **Theming**:
  - Template: `templates/makerspace-user-links-list.html.twig`.
  - CSS: `css/user-links.css`.
  - Library: `makerspace_user_links/user_links`.

## Member bar

Every logged-in page in the member (`makerspace_gin`) and public (d11) themes
starts with the member bar (`MemberBar`, injected by `hook_page_top`). It is
the navigation for everyone without `access navigation`; since 2026-10 only
staff roles (administrator, manager, special, librarian, event_management,
data) keep core's Navigation sidebar. Members, borrowers, facilitators,
instructors and content editors rely on the bar.

- Links come from three menus, all access-filtered: `member-nav` (primary),
  `your-dashboards` (the "Dashboards" dropdown; also the hub's role cards) and
  `member-account` (profile, billing, log out). They are code-defined in
  `makerspace_user_links.links.menu.yml`; staff can reorder or disable them in
  the menu UI. Give every link a `data-icon` (Bootstrap Icons name): on narrow
  screens the primary links show icons only.
- `options.mh_access_permission` hides a link from anyone without that
  permission, for destinations whose route does not gate access (CiviCRM) or
  is too broad (members hold `access administration pages`).
- `js/member-bar.js` measures the bar and sheds labels in two steps
  (`is-tight`, then `is-compact`) instead of fixed breakpoints, because Gin and
  d11 render the bar in different fonts.
- When adding a link to a sidebar menu, or removing one from these menus, run
  `lando drush php:script scripts/audit/member-bar-reachability.php` from the
  site repo: it fails if a non-staff test user can reach a link only through
  the sidebar.

## Building and Running

As a Drupal module, this project runs within a Drupal environment (typically via Lando).

- **Install/Enable**: `lando drush en makerspace_user_links`
- **Rebuild Cache**: `lando drush cr`
- **Configuration**: The block should be placed via the Drupal Block Layout UI or exported in configuration.

## Development Conventions

- **Strict Typing**: All PHP files should include `declare(strict_types=1);`.
- **Dependency Injection**: Services and plugins must use DI (e.g., `ContainerFactoryPluginInterface` for blocks).
- **Caching**: The `UserLinksBlock` uses granular cache contexts (`route`, `user.permissions`) and tags (`user:[uid]`) to ensure data remains fresh and personalized.
- **Link Definition**: A link definition should typically include `id`, `title`, `route_name` (or `uri`), `category`, and `weight`.

## Key Files

- `makerspace_user_links.module`: Implements `hook_theme` and the base `hook_makerspace_user_links_links`.
- `src/Service/UserLinkManager.php`: The main engine for link processing.
- `src/Plugin/Block/UserLinksBlock.php`: The UI entry point.
- `makerspace_user_links.api.php`: Documentation for the module's custom hooks.
