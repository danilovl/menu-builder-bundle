[![phpunit](https://github.com/danilovl/menu-builder-bundle/actions/workflows/phpunit.yml/badge.svg)](https://github.com/danilovl/menu-builder-bundle/actions/workflows/phpunit.yml)
[![downloads](https://img.shields.io/packagist/dt/danilovl/menu-builder-bundle)](https://packagist.org/packages/danilovl/menu-builder-bundle)
[![latest Stable Version](https://img.shields.io/packagist/v/danilovl/menu-builder-bundle)](https://packagist.org/packages/danilovl/menu-builder-bundle)
[![license](https://img.shields.io/packagist/l/danilovl/menu-builder-bundle)](https://packagist.org/packages/danilovl/menu-builder-bundle)

# MenuBuilderBundle

A Symfony bundle for managing site menus: backend storage, REST API, Twig
rendering, and a Vue 3 admin SPA.

## Features

- 3 pluggable storage backends — Doctrine, Symfony Cache pool, Redis
- Tree of arbitrary depth with cycle protection on move; ordering is strictly by `position` field — never by label
- 5 item types — `link`, `divider`, `heading`, `external` (auto `target=_blank`), `mega` (multi-column dropdowns)
- Visibility model: roles, allowed user identifiers, audience (always / authenticated / anonymous)
- Time-bound publish window (`publishedAt` / `unpublishedAt`)
- Per-locale labels and URIs (translations) plus optional Symfony Translator integration with `previewLocale` for the admin tree
- Soft delete with restore (Doctrine driver)
- Permission filtering via a Symfony security voter — extend it like any other voter
- Vue 3 admin: drag-drop reordering, inline rename (double-click), multi-select bulk actions, trash, JSON import/export, cross-menu search, 10 UI locales
- Live autocomplete in the editor: Symfony route catalog (`route_catalog.preload_limit` switches to lazy server search above the threshold), role picker from `security.role_hierarchy.roles`, user picker via pluggable `UserCatalogInterface`
- Twig rendering through overridable blocks; bundled themes for Bootstrap 5 and Inspinia (metismenu sidebar)
- 6 CLI commands including `danilovl:menu-builder:sync` to import items from `#[MenuItem]` PHP attributes on controllers


![General](/readme/general.png?raw=true "General")

![Access](/readme/access.png?raw=true "Access")

![Translation](/readme/translation.png?raw=true "Translation")


## Installation

```bash
composer require danilovl/menu-builder-bundle
```

`config/bundles.php`:

```php
return [
    // ...
    Danilovl\MenuBuilderBundle\MenuBuilderBundle::class => ['all' => true],
];
```

`config/routes/menu_builder.yaml`:

```yaml
menu_builder:
    resource: '@MenuBuilderBundle/Resources/config/routing.yaml'
```

`config/packages/menu_builder.yaml` — minimal:

```yaml
danilovl_menu_builder:
    storage:
        driver: doctrine        # doctrine | cache | redis
```

For the Doctrine driver, generate and run a migration after installing:

```bash
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

## Configuration reference

```yaml
danilovl_menu_builder:
    storage:
        driver: doctrine                # doctrine | cache | redis
        redis_dsn: '%env(REDIS_URL)%'   # required when driver=redis
        pool: cache.app                 # cache pool when driver=cache
        key_prefix: 'danilovl_menu_builder.'  # storage key prefix (cache/redis)
        ttl: ~                          # null = no expiration; set seconds to expire
    soft_delete: true                   # Doctrine only
    api:
        prefix: '/api/menu'
        public_cache_ttl: 0             # HTTP Max-Age on /api/menu/tree/{name}; 0 disables
    route_catalog:
        preload_limit: 500              # admin route picker: preload first N routes;
                                        # above this, switches to lazy server search
```

Roles surfaced in the admin "Required roles" picker come from
`security.role_hierarchy.roles` (a single container parameter — zero scan,
zero reflection). Declare every role you want available in the picker
under `role_hierarchy:` in `security.yaml` — orphan roles are fine:

```yaml
security:
    role_hierarchy:
        ROLE_ADMIN:     ROLE_USER
        ROLE_BILLING:   []
        ROLE_FEATURE_X: []
```

The bundle does not run a server-side render cache — menus are always built
fresh from storage. The only cache layer is HTTP `Max-Age` + `ETag` on the
public tree endpoint, controlled by `api.public_cache_ttl`. If you need
storage-level caching, choose `driver: cache` or `driver: redis` and set
`storage.ttl`.

## Twig rendering

All Twig functions are prefixed `danilovl_menu_builder_`:

```twig
{# default theme #}
{{ danilovl_menu_builder_render('main') }}

{# Bootstrap 5 navbar — bundled example theme #}
{{ danilovl_menu_builder_render('main', {
    template: '@MenuBuilder/menu/themes/bootstrap.html.twig',
    current_route: app.request.attributes.get('_route'),
}) }}

{# Inspinia / metismenu sidebar — bundled example theme #}
{{ danilovl_menu_builder_render('main', {
    template: '@MenuBuilder/menu/themes/inspinia.html.twig',
    current_route: app.request.attributes.get('_route'),
    locale: app.request.locale,
}) }}

{# custom theme #}
{{ danilovl_menu_builder_render('main', { template: 'menu/_my_theme.html.twig' }) }}

{# walk raw tree manually #}
{% for item in danilovl_menu_builder_tree('footer') %}
    <a href="{{ danilovl_menu_builder_url(item) }}">
        {{ danilovl_menu_builder_label(item) }}
    </a>
{% endfor %}

{# admin page bootstrap (loads Vue assets) #}
{{ danilovl_menu_builder_assets() }}
```

Full list of registered Twig functions:

| Function | Purpose |
| --- | --- |
| `danilovl_menu_builder_render(name, options)` | Render a full menu through the chosen theme |
| `danilovl_menu_builder_tree(name)` | Get the permission-filtered tree (array of `MenuItemInterface`) for manual walking |
| `danilovl_menu_builder_url(item, locale)` | Resolve `route` → URL or fall back to `uri`; supports per-locale URI override |
| `danilovl_menu_builder_label(item, locale)` | Resolve label, honoring `labelTranslated` + `translationDomain` and per-locale overrides |
| `danilovl_menu_builder_target(item)` | Item's `target` attribute (forces `_blank` for `external` items) |
| `danilovl_menu_builder_is_active(item, current_route)` | Whether this item matches the current route or any of its `dependentActiveRoutes` |
| `danilovl_menu_builder_assets()` | Print `<link>` + `<script>` tags for the Vue admin bundle |

### Bundled themes

| Theme | Path | Notes |
| --- | --- | --- |
| Default | `@MenuBuilder/menu/default.html.twig` | Override-friendly base; CSS classes prefixed `mb-menu__*` |
| Bootstrap 5 | `@MenuBuilder/menu/themes/bootstrap.html.twig` | Navbar layout; extends default |
| Inspinia | `@MenuBuilder/menu/themes/inspinia.html.twig` | Metismenu-compatible sidebar; extends default |

Themes are selected per-call via `template:` option — there is no global
"current theme" setting. Each theme accepts its own options (look at the
header comment of the theme file for the list — e.g. `nav_class`, `ul_id`,
`logo_inner` for Inspinia).

### Writing your own theme

`@MenuBuilder/menu/default.html.twig` exposes three top-level Twig blocks
that can be overridden via `{% extends %}`: `root`, `list`, `empty`.
Per-item rendering is delegated to macros (`render_item`, `render_link`,
`render_link_inner`, `render_icon`, `render_label`, `render_children`,
`render_mega`) — extending the template can replace the outer wrapper but
not the per-item HTML, since Twig blocks do not propagate through macro
boundaries. Top-level block scope: `items`, `options`, `locale`,
`current_route`, `depth`.

```twig
{% extends '@MenuBuilder/menu/default.html.twig' %}

{% block list %}
    <ul class="my-nav">
        {% for item in items %}
            <li>
                <a href="{{ danilovl_menu_builder_url(item, locale) }}">
                    {{ danilovl_menu_builder_label(item, locale) }}
                </a>
            </li>
        {% endfor %}
    </ul>
{% endblock %}
```

For full control over per-item HTML, copy the default template into your
project, edit the macros directly, and pass it via the `template:` option:

```twig
{{ danilovl_menu_builder_render('main', { template: 'menu/_my_theme.html.twig' }) }}
```

CSS class names default to `mb-menu__*` and can be overridden per call
via `options.css.{root,list,item,link,icon,label,children,mega,empty}`
without writing a custom template.

## REST API

### Public

| Method | Path | Response | Notes |
| --- | --- | --- | --- |
| `GET` | `/api/menu` | `{"menus": [{name, active}]}` | List all menus |
| `GET` | `/api/menu/tree/{name}` | `{"menu", "items"}` | Permission-filtered tree; supports `?maxDepth=N`, `?previewLocale=xx` |
| `GET` | `/api/menu/routes` | `{"items", "total", "matched", "limit", "truncated"}` | Symfony route catalog; `?q=` filter |
| `GET` | `/api/menu/roles` | `{"items"}` | Roles from `security.role_hierarchy.roles` |
| `GET` | `/api/menu/users` | `{"items", "matched", "truncated"}` | User search; `?q=&limit=` |
| `GET` | `/api/menu/config` | `{"apiPrefix"}` | Frontend bootstrap config |

### Admin (mount behind your own `ROLE_ADMIN` firewall)

| Method | Path | Body / Response |
| --- | --- | --- |
| `GET` | `/api/menu/admin/{name}/items` | Resp: `{"menu", "items"}`; supports `?previewLocale=xx` |
| `GET` | `/api/menu/admin/search?q=&limit=` | Resp: `{"items"}` (cross-menu search) |
| `POST` | `/api/menu/admin/items?previewLocale=xx` | Body: `CreateMenuItemRequest` |
| `PUT` / `PATCH` | `/api/menu/admin/items/{id}?previewLocale=xx` | Body: `UpdateMenuItemRequest` |
| `DELETE` | `/api/menu/admin/items/{id}` | — |
| `POST` | `/api/menu/admin/items/{id}/active` | Body: `{ "active": bool }` |
| `POST` | `/api/menu/admin/items/{id}/move` | Body: `{ "parentId", "position" }` |
| `POST` | `/api/menu/admin/items/{id}/duplicate` | Clones the subtree |
| `POST` | `/api/menu/admin/items/{id}/restore` | Restore from trash (Doctrine only) |
| `POST` | `/api/menu/admin/items/bulk-delete` | Body: `{ "ids": [...] }`; Resp: `{"deleted", "errors"}` |
| `POST` | `/api/menu/admin/items/bulk-active` | Body: `{ "ids": [...], "active": bool }`; Resp: `{"updated", "errors"}` |
| `GET` | `/api/menu/admin/{name}/trash` | Resp: `{"menu", "items"}` (soft-deleted items) |
| `PATCH` | `/api/menu/admin/menus/{name}` | Body: `{ "name" }`; Resp: `{"name"}` |
| `DELETE` | `/api/menu/admin/menus/{name}` | — |
| `POST` | `/api/menu/admin/menus/{name}/active` | Body: `{ "active": bool }`; Resp: `{"name", "active"}` |
| `GET` | `/api/menu/admin/menus/{name}/export` | JSON download |
| `POST` | `/api/menu/admin/menus/import` | Body: `{ "menu", "items", "overrideMenuName"? }` |

Request payloads are mapped through `#[MapRequestPayload]` DTOs and validated
with Symfony Validator. HTTP status codes use `Response::HTTP_*` constants —
404 for missing items, 422 for domain/validation errors, 201 on create.

Sample create payload:

```json
{
  "menuName": "main",
  "label": "Profile",
  "route": "app_profile",
  "icon": "fa fa-user",
  "iconImage": "/uploads/icons/profile.svg",
  "type": "link",
  "visibility": "authenticated",
  "requiredRoles": ["ROLE_USER"],
  "cssClasses": ["nav-item-highlight"],
  "publishedAt": "2026-01-01T00:00:00+00:00",
  "unpublishedAt": "2026-12-31T23:59:59+00:00",
  "parentId": null,
  "position": 0
}
```

## Vue admin

A bundled SPA mounts onto a DOM element and talks to the REST API.
The dashboard route is part of the same `routing.yaml` you already
imported during installation — no extra route file is needed.

The dashboard is registered at `/danilovl/menu-builder/dashboard`
(name `danilovl_menu_builder_dashboard_index`). Either expose it
directly behind your admin firewall, or mount the SPA into your own
layout via Twig:

```twig
{{ danilovl_menu_builder_assets() }}
<div id="menu-admin" data-menu-admin data-config-url="{{ path('danilovl_menu_builder_api_config_index') }}"></div>
```

Or render the bundled dashboard route directly:

```php
#[Route('/admin/menu', name: 'admin_menu')]
#[IsGranted('ROLE_ADMIN')]
public function admin(): Response
{
    return $this->forward('Danilovl\\MenuBuilderBundle\\Controller\\DashboardController::index');
}
```

UI capabilities:

- Tree with drag-drop reorder (cross-level)
- Inline rename via double-click on the label
- Bulk select mode with mass activate / deactivate / delete
- Trash panel with restore (Doctrine driver)
- Cross-menu search by label / URI / route
- JSON import / export per menu
- Multi-locale label preview, language switcher (10 locales)
- Edit highlight on the tree row matching the form

## Programmatic API

```php
use Danilovl\MenuBuilderBundle\Service\MenuManager;

final class SomeService
{
    public function __construct(private MenuManager $menus) {}

    public function bootstrap(): void
    {
        $home = $this->menus->create([
            'menuName' => 'main',
            'label' => 'Home',
            'route' => 'app_home',
            'icon' => 'fa fa-home',
        ]);

        $this->menus->create([
            'menuName' => 'main',
            'label' => 'About',
            'route' => 'app_about',
            'parentId' => $home->getId(),
        ]);

        $this->menus->setMenuActive('main', true);
        $tree = $this->menus->getTree('main'); // permission-filtered for current user
    }
}
```

Item ordering is always governed by the `position` field — never by label
or any other attribute. Renaming an item never moves it; drag-drop reorder
in the admin or `MenuManager::move()` is the only way to change order.

## Auto-discover from controllers

Add `#[MenuItem]` attributes on controller actions and run
`danilovl:menu-builder:sync` to upsert them into storage:

```php
use Danilovl\MenuBuilderBundle\Attribute\MenuItem;
use Symfony\Component\Routing\Attribute\Route;

final class ProfileController
{
    #[Route('/profile', name: 'app_profile')]
    #[MenuItem(menu: 'main', label: 'Profile', position: 10, icon: 'fa fa-user', requiredRoles: ['ROLE_USER'])]
    public function index(): Response { /* ... */ }
}
```

```bash
php bin/console danilovl:menu-builder:sync --dry-run   # preview
php bin/console danilovl:menu-builder:sync             # apply
```

The scanner walks `RouterInterface::getRouteCollection()` and reads
`#[MenuItem]` attributes from each controller method. Items are matched
against existing entries by route or label and skipped if already present.

## CLI commands

| Command | Purpose |
| --- | --- |
| `danilovl:menu-builder:list` | List menus with item counts and active state |
| `danilovl:menu-builder:export <name> [-o file.json]` | Export a menu to JSON (stdout or file) |
| `danilovl:menu-builder:import <file> [--as=newName]` | Import a menu, optionally renaming it |
| `danilovl:menu-builder:duplicate <source> <target>` | Clone a whole menu under a new name |
| `danilovl:menu-builder:validate-routes [name]` | Find items pointing at non-existent Symfony routes |
| `danilovl:menu-builder:sync [--dry-run]` | Sync items from `#[MenuItem]` attributes on controllers |

## Symfony Translator integration

Each menu item carries two related fields:

- `labelTranslated: bool` — when `true`, the `label` field is treated as a
  translator key rather than literal text.
- `translationDomain: ?string` — passed to `trans()`; `null` falls back to
  the default domain (`messages`).

When rendering through Twig, `MenuExtension::label()` resolves the value
through Symfony's translator. The admin tree shows a 🌐 indicator next to
items that use a translator key. The admin API also returns a precomputed
`resolvedLabel` field on each item — translated for the current request
locale, or for whatever `?previewLocale=xx` you pass.

```twig
{# pick locale per call #}
{{ danilovl_menu_builder_render('main', { locale: 'fr' }) }}
```

```php
// Custom controller — render the same menu in user's locale
return $this->render('layout.html.twig', [
    'locale' => $request->getLocale(),
]);
```

```yaml
# translations/messages.en.yaml
menu:
    home: 'Home'
    about: 'About us'
```

Set `labelTranslated: true` on the item, store the key (`menu.home`) in the
`label` field, and the renderer resolves it.

## User catalog (allowed users autocomplete)

`allowedUsers` on each item is a free-form list of identifiers (emails,
usernames, UUIDs — your call). The admin form ships with live autocomplete
that calls `GET /api/menu/users?q=…`. The bundle does not know how to look
up your users, so the default implementation (`NullUserCatalog`) returns
empty results — the field still works as a manual tag input.

To wire suggestions, implement `UserCatalogInterface` and alias it:

```php
namespace App\Menu;

use Danilovl\MenuBuilderBundle\Service\UserCatalogInterface;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineUserCatalog implements UserCatalogInterface
{
    public function __construct(private EntityManagerInterface $em) {}

    /**
     * @return array<int, string>
     */
    public function search(string $term, int $limit): array
    {
        $rows = $this->em->createQueryBuilder()
            ->select('u.email')
            ->from(User::class, 'u')
            ->where('LOWER(u.email) LIKE LOWER(:q)')
            ->setParameter('q', '%' . $term . '%')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();

        return array_column($rows, 'email');
    }
}
```

```yaml
# config/services.yaml
services:
    Danilovl\MenuBuilderBundle\Service\UserCatalogInterface:
        alias: App\Menu\DoctrineUserCatalog
```

The endpoint clamps `limit` to 100 and only queries when `q` is non-empty.

## Permissions / extending the voter

`MenuItemVoter` decides whether the current user can see an item, based on
visibility mode, required roles, allowed users, and publish window. Override
or augment it like any Symfony voter — register your own voter listening on
the `danilovl_menu_builder.see` attribute and return `true` / `false` based on your
domain rules. Validation on save also rejects unknown Symfony route names via
`RouteExistenceChecker`.

## Events

The bundle dispatches:

- `MenuItemSavedEvent` — on create and update (carries `isNew`)
- `MenuItemDeletedEvent` — on delete
- `MenuItemMovedEvent` — on move

```php
use Danilovl\MenuBuilderBundle\Event\MenuItemSavedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: MenuItemSavedEvent::class)]
public function onSave(MenuItemSavedEvent $event): void
{
    $this->logger->info('Menu changed', ['id' => $event->item->getId()]);
}
```

## Storage backends

| Driver | Backed by | Soft delete | Use case |
| --- | --- | --- | --- |
| `doctrine` | RDBMS via Doctrine ORM | yes | Default; production with persistence |
| `cache` | Symfony Cache pool | no | Lightweight, no separate Redis instance |
| `redis` | `\Redis` directly | no | High-throughput, no DB |

Switch by setting `storage.driver`. The `getDeletedItems` and `restore`
operations are only meaningful for `doctrine`; other drivers return empty.

## Tests

```bash
composer install
composer tests
composer phpstan
composer cs-fixer-check
```

Unit tests live under `tests/Unit/` and cover the model layer
(`DetachedMenuItem`, `MenuItemType`, `MenuItemVisibility`), in-memory
storage CRUD/move/search (via `MockStorage`), the `MenuBuilder` permission filter,
all three `Matcher` voters (URI / route / regex) plus the cache, and pure services
(`RouteCatalog`, `RoleCatalog`, `RouteExistenceChecker`,
`MenuItemNormalizer`, `NullUserCatalog`). Smoke tests for `MenuManager` go
through `MockStorage` end-to-end. No database or HTTP layer is required.


## License

The MenuBuilderBundle is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).