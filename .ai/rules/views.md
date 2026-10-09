---
paths:
  - 'resources/views/**'
  - 'packages/*/resources/views/**'
---

# Views

## Use the Blade components; never hand-roll their markup
Before writing markup in any view, check the component table in the design-system skill (.ai/skills/design-system/SKILL.md) and use the component: <x-banner> (with its actions slot) for a status or warning strip that stays, <x-alert> for a short message, <x-card> (inset when nested; accent, footer, sticky-footer; href for a whole card as a link, never an <a> around a card) for a panel, <x-empty-state> for a dashed "nothing here yet" panel, <x-badge>, <x-button.*> intents (or <x-button> for a one-off), <x-button-group> for a segmented control (never daisyUI join; in a Filament form, ToggleButtons->grouped()), and <x-avatar icon> for a tinted icon tile. Never raw daisyUI classes (btn, alert, card, badge, join, ui-banner) or a strip built from border-l-4 border-{status} or bg-{status}/NN. If a component almost fits, extend it (and its docs) rather than copy it. tests/Feature/Components/ComponentUsageTest.php fails on hand-rolled markup; a deliberate exception goes in its ALLOWED list with the reason.

## Muted text is `text-muted`
Use `text-muted` (the utility in `resources/css/theme/tokens.css`) for descriptions, breadcrumbs, labels and placeholders. For text, never `text-base-content/60` or a lighter mix (decorative icons may go lighter). It is the one muted mix tested to clear AA on `base-100` and `base-200` (`ThemeContrastTest`); `ComponentUsageTest` flags the raw `/60` in views and components, and anything at or below 60% in the theme CSS.

## Field hints go under the field, never above it
A form field reads label, field, hint, then error, top to bottom. Filament's helperText() already renders below the field; any hand-rolled field (Blade or a JavaScript widget) follows the same order, and a fieldset of choices puts its hint and error after the choices. Never put a hint between the label and the field (settled 2026-10-09).
