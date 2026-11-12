---
paths:
  - 'resources/views/**'
  - 'packages/*/resources/views/**'
---

# Views

## Use the Blade components; never hand-roll their markup
Before writing markup in any view, check the component table in the design-system skill (.ai/skills/design-system/SKILL.md) and use the component: <x-banner> (with its actions slot) for a status or warning strip that stays, <x-alert> for a short message, <x-card> (inset when nested; accent, footer, sticky-footer; href for a whole card as a link, never an <a> around a card) for a panel, <x-empty-state> for a dashed "nothing here yet" panel, <x-badge> for a kind of thing (a role) and <x-status> for a state (active, suspended, enabled: a dot and its label, never a badge; ->status() in place of ->badge() on a Filament column or entry), <x-button.*> intents (or <x-button> for a one-off), <x-button-group> for a segmented control (never daisyUI join; in a Filament form, ToggleButtons->grouped()), and <x-avatar icon> for a tinted icon tile. Never raw daisyUI classes (btn, alert, card, badge, status, join, ui-banner) or a strip built from border-l-4 border-{status} or bg-{status}/NN. If a component almost fits, extend it (and its docs) rather than copy it. tests/Feature/Components/ComponentUsageTest.php fails on hand-rolled markup; a deliberate exception goes in its ALLOWED list with the reason.

## A hand-built table goes in `<x-table-scroll>`
Never a bare `overflow-x-auto` wrapper: the component gives the table the same column header behaviour, scroll rail and flush tab panel as a Filament table. See tables.md, "Long and wide tables".

## Muted text is `text-muted`
Use `text-muted` (the utility in `resources/css/theme/tokens.css`) for descriptions, breadcrumbs, labels and placeholders. For text, never `text-base-content/60` or a lighter mix (decorative icons may go lighter). It is the one muted mix tested to clear AA on `base-100` and `base-200` (`ThemeContrastTest`); `ComponentUsageTest` flags the raw `/60` in views and components, and anything at or below 60% in the theme CSS.

## Field hints go under the field, never above it
A form field reads label, field, hint, then error, top to bottom. Filament's helperText() already renders below the field; any hand-rolled field (Blade or a JavaScript widget) follows the same order, and a fieldset of choices puts its hint and error after the choices. Never put a hint between the label and the field (settled 2026-10-09).

## A page form's actions go in `<x-form-actions>`
The submit row at the end of a page form (Livewire views) is `<x-form-actions>`, never a hand-rolled flex row: it sticks to the bottom of the screen on a long form so Save stays in reach, and sits in place on a short one. Main action first, then Cancel or Back; other actions on the record go in its `secondary` slot (far end of the row; behind a "More actions" button on a phone, so they never take a row of their own). Dialogs keep the `<x-modal>` footer and cards their `footer` slot. `ComponentUsageTest` fails on a submit outside these (settled 2026-10-23).

## Tabs follow the shared scrolling default at every screen size
x-tabs-nav defaults scrollable to true. Keep one horizontal row for every tab count, with native horizontal scrolling, active-tab reveal, and accessible arrow buttons beside the viewport, shown only when the tabs overflow (a strip that fits keeps the full width, so short strips never scroll for the arrows' sake). No swipe banner, mobile grid, dropdown, or Sections disclosure. Pages omit scrollable so this behaviour stays central to the component; explicit false remains a plain wrapping fallback (settled 2026-10-25).

## Visible text is sentence case
Labels, headings, buttons, aria-labels and titles are sentence case: capitalise only the first word, proper nouns and acronyms ("Save changes", "My profile", "Set up two factor authentication"). Prefer a lang key over a literal __('...') string. Full rule: .ai/rules/lang.md (#16).

## Show and hide is a chevron that turns down
Content shown and hidden in place (a row's entries, a card's details, a section) opens with a chevron before its label: `<x-heroicon-m-chevron-right>` (or -o- to match the label's weight) with `motion-safe:transition-transform` and rotate-90 when open, aria-hidden. The label names what opens ("8 entries"), not the action: the chevron and aria-expanded say whether it's open, so it never swaps between "Show" and "Hide". Use a native `<details>`/`<summary>` (`group-open:rotate-90`, the marker hidden) when the toggle sits directly above its content; when it can't (the content sits outside the toggle's parent, say below a whole table row), a `<button aria-expanded aria-controls>` with `group-aria-expanded:rotate-90`. Remember what's open for the tab in sessionStorage (in try/catch: it can be unavailable), so Back finds the page as it was left. Alpine reads x-init as an expression, so wrap the try in an arrow function (`x-init="(() => { try { ... } catch (error) {} })()"`): a bare try fails silently and nothing reopens. A table row that opens a detail row takes it as its first cell, before the checkbox: an icon button named for what it opens ("Details for Jo Smith"), with aria-expanded (the theme demo's maximalist tables). A Filament table's collapsible Panel gets this without anything per table: the icon alias in AppServiceProvider and filament-table.css move Filament's toggle to the start of the row and turn it. The sidebar's nav groups keep their chevron at the end of the heading but turn it the same way, right when closed and down when open. Not for menus, dropdowns, drawers or the table toolbar's Filters panel (a trailing chevron-down on a button), which open over the page or are a control panel (settled 2026-11-13).
