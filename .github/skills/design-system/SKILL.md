---
name: design-system
description: 'Style a Blade view or component using this app''s Tailwind + daisyUI design system. Use when asked to style a page, pick colors/classes for UI, add a badge/alert/avatar/banner, use the design system, or match existing look and feel.'
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

## Spacing & components

- Use `gap-*` utilities for spacing items in a flex/grid list, not margins.
- Check `resources/css/theme/components/*.css` (`button-group.css`,
  `floating-label.css`, `tabs.css`, `toolbar.css`, `sidebar-layout.css`,
  `typography.css`, `shadows.css`) before writing custom CSS — most layout
  primitives already exist there.
- Reuse existing Blade components before writing a new one (check
  `resources/views/components/*`).
