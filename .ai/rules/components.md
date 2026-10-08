---
paths:
  - 'resources/views/components/**'
---

# Components

## Blade UI components are anonymous, not class-based
UI components are anonymous Blade components using `@props` — do not add class-based components under `app/View/Components`.

## Buttons: reach for the semantic component, not a colour
Prefer the five intent components over `<x-button color="…">` in app markup — the intent, not the colour, is the API, and it keeps every instance identical. This includes **form submits** (`<x-button.action type="submit">`, never a bare `<x-button>` or `<x-filament::button>`). See [docs/design-system.md](../../docs/design-system.md).

- `<x-button.action>` — affirmative do/add/save/submit/confirm (primary). The default.
- `<x-button.secondary>` — a non-primary alternative next to an action (solid **secondary** colour — action→primary, secondary→secondary; distinct from `.cancel`'s neutral outline): show/regenerate recovery codes, an alternate CTA.
- `<x-button.cancel>` — dismiss/abort a form or modal (neutral outline). Defaults its label to "Cancel".
- `<x-button.back>` — navigate back to the previous screen (neutral ghost + leading arrow, one step below `.cancel`). Defaults its label to "Back"; usually given an `href`.
- `<x-button.danger>` — destructive or irreversible: delete, force-delete, purge, force-disable 2FA, suspend, revoke. **Not just "delete".**
- `<x-button.warning>` — consequential but *reversible/non-destructive*: impersonate, grant super-admin, reset password.
- `<x-button.icon>` — square (or `circle`) icon-only chrome: menu toggles, modal close, pin/unpin. Plain ghost, no colour tint. **Always give it an `aria-label` or `title`.**

Rubric for danger vs warning: destroys/removes/cuts off access → `danger`; powerful but reversible → `warning`; otherwise → `action`. `<x-button>` stays the low-level primitive these wrap; use it directly only for a standalone navigation CTA where a deliberate primary/primary-outline emphasis (not one of the intents above) is the point — e.g. a landing-page "Go to dashboard" / "Register" pair. There are no `info`/`success` buttons by design — those colours are for status surfaces (alerts, badges), not actions.

Group a set of sibling actions (a sidebar "Actions" card, a panel's action row) in **`<x-action-list>`** rather than hand-rolling a flex wrapper — it tiles the buttons horizontally, wraps them, and grows each to share the row width (a lone button on a row fills it). Works with both `<x-button.*>` and Filament `{{ $action }}` output.

For the **Filament actions** that go in one, use **`App\Support\Filament\AdminAction::make()`** in place of `Action::make()` — it bakes in the app's action-list treatment (soft, small) so every screen matches. An action built by a shared factory (one that is solid in a list header elsewhere) gets the same look via `AdminAction::styleForList($action)`. Set colour/icon/label per action for intent; modal defaults still come from `AppServiceProvider`. See `app/Livewire/Admin/Users/ShowUser.php` for the reference usage. Blade `<x-button.*>` in an action list take `variant="soft" size="sm"` to match.

Emphasis by placement: the page or list header's primary action (New user, Edit user) is **solid**; every button in an Actions card or action row is **soft**, coloured by intent; cancel/back stay outline/ghost. The same action can be solid in one place and soft in another.

**Never use `<x-filament::button>` for a hand-placed button** — it exists only where Filament renders it. A Filament PHP `Action` is correct for table row/bulk/header actions and anything that opens a `->requiresConfirmation()` or `->schema()` modal; everything else is a `<x-button.*>`.

## Modals: use the shell, never hand-roll a `<dialog>` / daisyUI `.modal`
Build every modal on `<x-modal>` (it is a native `<dialog>`, so focus-trap, Esc, focus-restore and an inert background come free). It requires a `wire:model` boolean and takes `variant` (neutral | danger | warning | info | success), `title`, `description`, optional `submit` (wraps the body in a `<form wire:submit>`), and a `footer` slot. Footer buttons go **cancel-left, action-right**: the shell enforces this and `<x-confirm-modal>` picks the affirmative button from the variant. For an "are you sure?", prefer `<x-confirm-modal wire:model confirm="method" variant …>`. Dismiss from markup with the Alpine `open = false`. The one exception is the app shell's menu drawer (it slides in and shares its navigation with the pinned column), which is a hand-built modal dialog; opening any `<x-modal>` (it dispatches `ui-modal-opened`) or Filament modal closes it (a Filament modal opened by an id-less trigger slot doesn't announce, but its trigger is behind the drawer anyway).

Open a modal by setting its bound property, e.g. a trigger `x-on:click="$wire.showDelete = true"`, or a Livewire method (`$this->showDelete = true`) — **never `wire:click="$set('showDelete', true)"`**: `$set` on a property that is also Alpine-`entangle`d (which the shell does) fights the entangled value and the modal flickers open then closes. See [docs/design-system.md](../../docs/design-system.md).

## Button wrappers forward href as a prop, never through $attributes
Bound attributes are escaped when they enter the attribute bag, and x-button escapes its href prop again, so a forwarded href with a query string rendered as &amp;amp;. Each x-button.* wrapper declares @props(['href' => null]) and passes :href="$href". Do the same in any new wrapper around x-button, and for any other prop the inner component echoes.
