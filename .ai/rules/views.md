---
paths:
  - 'resources/views/**'
  - 'packages/*/resources/views/**'
---

# Views

## Use the Blade components; never hand-roll their markup
Before writing markup in any view, check the component table in the design-system skill (.ai/skills/design-system/SKILL.md) and use the component: <x-banner> (with its actions slot) for a status or warning strip that stays, <x-alert> for a short message, <x-card> (inset when nested; accent, footer, sticky-footer) for a panel, <x-badge>, <x-button.*> intents (or <x-button> for a one-off), <x-button-group> for a segmented control (never daisyUI join; in a Filament form, ToggleButtons->grouped()), and <x-avatar icon> for a tinted icon tile. Never raw daisyUI classes (btn, alert, card, badge, join, ui-banner) or a strip built from border-l-4 border-{status} or bg-{status}/NN. If a component almost fits, extend it (and its docs) rather than copy it. tests/Feature/Components/ComponentUsageTest.php fails on hand-rolled markup; a deliberate exception goes in its ALLOWED list with the reason.
