---
name: design-system
description: 'Style a Blade view or component using this app''s Tailwind + daisyUI design system. Use when asked to style a page, pick colors/classes for UI, add or write markup for a card/badge/alert/avatar/banner/button/page header/stats card/table header/tabs, use the design system, or match existing look and feel — check before writing any new Blade markup.'
---

# Design system (Tailwind 4 + daisyUI 5)

Living reference: `packages/theme-demo/resources/views/livewire/component-gallery.blade.php`
(and sibling `tables/`, `forms/`, `tab-content/` views) renders every themed
component with real markup — check it for a working example before writing
new markup by hand. Theme source lives in `resources/css/theme/*`.

## Colors — the one rule that differs from most Tailwind apps

**No shade-ramp utilities.** There is no `bg-primary-500`, `text-primary-700`,
etc. — only the 8 fixed daisyUI semantic colors: `primary`, `secondary`,
`accent`, `neutral`, `info`, `success`, `warning`, `error` (+ `base-100/200/300`).
Each has a matching `-content` color for text-on-that-background
(`text-primary-content` on a `bg-primary` surface).

Defined once in `resources/css/theme/tokens.css` (two daisyUI `@plugin
"daisyui/theme"` blocks — light `boilerplate` + dark `boilerplate-dark`).
Changing a brand color is a one-line edit there; every component using it
(`btn-primary`, `badge-success`, `alert-error`, ...) updates automatically.

### Custom color-spanning utilities

`resources/css/theme/components/ui/colors.css` adds three extra utilities
per color (hand-maintained, one block per color — only 8, no codegen):

| Utility | Purpose |
|---|---|
| `bg-soft-{color}` | Tinted background (`color-mix` at 8% into `base-100`) — use for subtle section backgrounds |
| `avatar-{color}` (compose with `avatar-solid`/`avatar-soft`/`avatar-ghost`/`avatar-outline`) | Colored avatar, variant applied separately |
| `ui-banner-{color}` | Sets `--ui-banner-color` for the banner component |

## Dark mode

If an existing page/component supports dark mode, new ones must too —
typically via daisyUI's `dark:` variant or the theme's own `@variant dark`
overrides (see `avatar-soft` in `colors.css` for a pattern that adjusts
mix strength in dark mode rather than hardcoding a second color).

## Spacing

- Use `gap-*` utilities for spacing items in a flex/grid list, not margins.
- Check `resources/css/theme/components/*.css` (`button-group.css`,
  `floating-label.css`, `tabs.css`, `toolbar.css`, `sidebar-layout.css`,
  `typography.css`, `shadows.css`) before writing custom CSS — most layout
  primitives already exist there.

## Components — always prefer these over hand-rolled markup

Check this table before writing raw daisyUI/Filament markup in a Blade view.
Every component lives in `resources/views/components/*` and is globally
available as `<x-name>` — this applies whether you're writing a new view or
editing an existing one: if a view you're touching has raw markup matching a
row below, convert it to the component rather than leaving it as-is. Only
hand-write the equivalent markup when nothing here covers the need (and
consider adding a component if the same markup would repeat more than twice,
per the Blade instructions).

This is enforced: `tests/Feature/Components/ComponentUsageTest.php` fails on
raw `btn` / `alert` / `card` / `badge` / `ui-banner` classes and on status
strips built from a coloured border or tint, outside
`resources/views/components`. A deliberate exception goes in its `ALLOWED`
list with the reason. See `.ai/rules/views.md`.

### Content

| Component | Use instead of | Key props |
|---|---|---|
| `<x-card>` | `<div class="card bg-base-100 border border-base-300">…` section wrapper | `title`, `type` (`default` bold title / `panel` smaller muted heading — Users Show page panel cards; picks `titleClass` for you, don't guess a heading class), `bordered` (default true — every card should have a border; only false when nesting a card inside another card), `bodyClass` (default `gap-3`), `accent` (a daisyUI colour for a thick top edge marking status, e.g. `warning` on a needs-attention card; text stays body colour); `href` (the whole card becomes the link, with hover and focus states; never wrap a card in an `<a>`, and nothing interactive inside it); slots: `actions` (e.g. a "View all" link, rendered inline with the title), `footer` (an action row under a divider; add `sticky-footer` to keep it in view while a long card scrolls) |
| `<x-empty-state>` | a dashed `rounded-box border border-dashed` "nothing here yet" panel: an empty list, a page still to come, a search that found nothing | `icon` (shown via `<x-avatar icon>`), `title`; slots: default (the explanation), `actions` |
| `<x-alert>` | `<div class="alert ...">` — short-lived flash/inline message | `color` (default `info`), `variant` (default `soft`) |
| `<x-banner>` | persistent contextual banner (announcements, "trial ending") **and any in-page status or warning strip that stays on screen, with or without buttons** ("Check these are the same person", "Not merged yet" with Back and Merge, "No email address" with Add details). Never hand-build one from a coloured border or tint. Not for flash messages, use `<x-alert>` for those | `variant` (default `info`), `dismissible`; slots: default (body), `actions` (wrap under the body on narrow screens) |
| `<x-badge>` | `<span class="badge ...">`; pass a Filament enum (`HasColor`/`HasLabel`) as `value` to skip color/label | `value`, `color`, `variant` (default `soft`), `size` |
| `<x-avatar>` | `<div class="avatar">…` initials/photo circle, **and any tinted icon tile** (a coloured square or circle behind an icon, e.g. a card or banner header): `<x-avatar icon="heroicon-o-users" color="warning" square />` | `user` (model with `->name`/`->profile_photo_url`), `name`, `src`, `size`, `variant` (default `soft`), `color`, `square`, `icon` (a heroicon name, shown instead of initials) |
| `<x-button>` | `<button class="btn ...">` / `<a class="btn ...">` | `color` (default `primary`), `variant` (default `solid`), `size`, `href`, `disabled`, `type`, `surface` (fills an outline/dash button with base-100 at rest, for a tinted panel such as an inset card) |
| `<x-button-group>` + `<x-button-group.item>` | a segmented control: a row of buttons sharing borders, one chosen (status toggles, quick filters, a theme switcher, pagination). **Never daisyUI's `join`.** In a Filament form, a single choice is `ToggleButtons::make()->grouped()`, drawn to match | group: `label` (for screen readers), `block`, `quiet` (inside a menu), `size` (`sm`); item: `active`, `color` (`primary`, `danger`, `neutral`), `href`, `type` |
| `<x-stats-card>` | dashboard label/value block | `label`, `value`, `color`, `footerColor`; slots: `icon`, `footer` |
| `<x-page-header>` | title + description + right-aligned actions row at the top of a page | `title`, `description`; slot: `actions` |
| `<x-form-input>` | labelled `<input>` (text/email/password/number/date/...) | `name` (id/for/@error key), `label`, `floating` (default true — floating label vs plain label above); rest of attrs (`type`, `wire:model`, ...) pass through to the `<input>`; `class` sizes the wrapper (e.g. `md:col-span-2`), not the input |
| `<x-form-textarea>` | labelled `<textarea>` | same props as `<x-form-input>`; slot is initial content |
| `<x-form-select>` | labelled `<select>` | same props as `<x-form-input>`; slot is the `<option>` list |

### Tables (pairs with a Filament resource table)

| Component | Use instead of | Key props |
|---|---|---|
| `<x-table-header>` | Filament's native table header/search/filter row (hidden via `filament-table.css`) | slots: `search`, `filters`, `counts`, `actions` |
| `<x-table-search>` | search input inside a `<x-table-header>` `search` slot | `model` (default `tableSearch`), `label`, `debounce` |
| `<x-table-filter-select>` | filter `<select>` inside a `<x-table-header>` `filters` slot | `model`, `label`, `options`, `placeholder` |
| `<x-listbox>` | a `<x-table-filter-select>` whose options need a coloured dot (a native `<select>` can't show one); works in any Livewire toolbar | `model`, `label`, `options`, `dots` (value => dot classes, e.g. `bg-primary`), `placeholder` |
| `<x-date-picker>` | any dropdown date or date-range picker (filters, toolbars): one component, not a variant per screen. Cally calendar; optional shortcut presets; read the value with `App\Support\DateRange::parse()` | `model`, `label`, `mode` (`range`/`single`), `presets` (key => label), `pick-label`, `placeholder`, `clearable`, `today`, `min`, `max` |
| `<x-table-trash-toggle>` | active/trashed record-count toggle inside a `counts` slot | `model`, `value`, `activeCount`, `trashedCount` |
| `<x-table-empty-trash-action>` | destructive header action button inside an `actions` slot | `action` (Filament action method name) |

### Tabs

| Component | Use instead of | Key props |
|---|---|---|
| `<x-tabs-nav>` | tabs as a bordered "island" attached to their own panel (never a floating bar over a separate box) | `scrollable` (default true); slot: `content` |
| `<x-tabs-item>` | a single `role="tab"` link | `active`, `href`, `icon`, `badge` |

### Admin shell (usually wired via a registry, not hand-written in a page)

- `<x-nav-group>` / `<x-nav-item>` — sidebar nav; add entries via
  `App\Support\Navigation\AdminNav` (see the `add-nav-item` skill) instead of
  placing these directly in a page view.
- `<x-breadcrumbs>`, `<x-application-logo>`, `<x-header-menu>`,
  `<x-impersonation-banner>`, `<x-theme-init-script>` — already wired into
  the admin/guest layouts; reuse the layout rather than duplicating these.
