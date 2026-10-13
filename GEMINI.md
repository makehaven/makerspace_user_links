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

Every logged-in page in the member (`makerspace_gin`) theme starts with the
member bar (`MemberBar`, injected by `hook_page_top`). It is the navigation
for everyone without `access navigation`; since 2026-10 only staff roles
(administrator, manager, special, librarian, event_management, data) keep
core's Navigation sidebar. Members, borrowers, facilitators, instructors and
content editors rely on the bar.

- Layout (JR 2026-10-13): gold "Member Resources" button (node 619) at the
  start, the MakeHaven wordmark centred (not a link), then Dashboards ▾,
  search, "Public Website", the viewer's name (opens My Membership, node
  5737) and the `member-account` buttons (Switch back while masquerading,
  Log out). No Make / Learn / Connect / Grow dropdowns: Member Resources
  lists every journey (`journey-*` menus) in full.
- The PUBLIC (d11) theme does not render the bar: its header already has a
  navigation bar and a search box. Logged-in non-staff get one gold "Member
  Resources" button (plus Switch back while masquerading) in the header's
  red top strip, added by `makerspace_user_links_preprocess_page()`
  (`MemberBar::buildPublicReturn()`, `templates/member-return.html.twig`).
- Links come from access-filtered menus defined in
  `makerspace_user_links.links.menu.yml` (staff can reorder or disable them
  in the menu UI): `member-nav` (optional extra links beside Member
  Resources; empty by default), `your-dashboards` (the "Dashboards" dropdown
  and the "Your Dashboards" cards on Member Resources, one entry per role
  hub) and `member-account`. Give every link a `data-icon` (Bootstrap Icons
  name).
- `options.mh_access_permission` hides a link from anyone without that
  permission, for destinations whose route does not gate access (CiviCRM) or
  is too broad (members hold `access administration pages`).
  `options.mh_access_callback` names a static check that takes the account
  (e.g. `GrowAccess::visible` for the Business dashboard). Both the bar and
  `RoleMenuCardsBlock` honour both options.
- `js/member-bar.js` measures the bar and sheds width in steps (`is-tight`:
  search input becomes an icon; `is-compact`: labels go; `is-crowded`: the
  wordmark goes) whenever the end group would touch the centred wordmark.
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
