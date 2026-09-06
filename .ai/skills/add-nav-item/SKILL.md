---
name: add-nav-item
description: 'Add or edit a link, group, or section in the admin sidebar nav. Use when asked to add a nav item, add a sidebar link, register a navigation entry, add a menu group, or make a page reachable from the sidebar.'
---

# Add a sidebar nav item

Full reference: [docs/navigation.md](../../../docs/navigation.md) — read it
before making changes, it covers permissions, active-state, and rendering
in detail. This skill is the short procedure.

## Procedure

1. Decide whether this is core (ships by default) or an add-on
   (feature-flagged / a local package like `packages/theme-demo`).
   - Core → edit `app/Support/Navigation/AdminNav.php`'s `define()` method.
   - Add-on → call the same registry from the add-on's own service
     provider `boot()` (see `packages/theme-demo/src/ThemeDemoServiceProvider.php`
     for a real example).
2. A single link: `NavRegistry::item($name)->label(...)->route(...)->icon(...)->order(...)`.
3. A link inside a section: `NavRegistry::group($groupName)->add(NavItem::make($name)->label(...)->route(...)->icon(...))`.
   Calling `group()` with an existing name returns the *same* group — use
   this to add into `management` etc. rather than creating a duplicate
   section.
4. Gate visibility with `->can('permission-name')` (a `SystemPermission`
   value) on the item or group — omit it entirely for always-visible.
5. Icons are heroicon component names (e.g. `heroicon-o-user`).
6. Don't hand-set a group's active state unless it needs to diverge from
   the union of its items' `active()` routes — the default already keeps
   the section expanded across all its children's routes.

## Verify

Grep `NavRegistry::` to confirm no duplicate registration, then check the
rendered sidebar (`admin-sidebar-nav.blade.php` renders via
`NavRegistry::resolve(auth()->user())`).
