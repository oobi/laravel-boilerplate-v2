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

**Never use `<x-filament::button>` for a hand-placed button** — it exists only where Filament renders it. A Filament PHP `Action` is correct for table row/bulk/header actions and anything that opens a `->requiresConfirmation()` or `->schema()` modal; everything else is a `<x-button.*>`.

## Modals: use the shell, never hand-roll a `<dialog>` / daisyUI `.modal`
Build every modal on `<x-modal>` (native `<dialog>` → focus-trap, Esc, focus-restore and inert background come free). It requires a `wire:model` boolean and takes `variant` (neutral | danger | warning | info | success), `title`, `description`, optional `submit` (wraps the body in a `<form wire:submit>`), and a `footer` slot. Footer buttons go **cancel-left, action-right** — the shell enforces this and `<x-confirm-modal>` picks the affirmative button from the variant. For an "are you sure?", prefer `<x-confirm-modal wire:model confirm="method" variant …>`. Dismiss from markup with the Alpine `open = false`.

Open a modal by setting its bound property, e.g. a trigger `x-on:click="$wire.showDelete = true"`, or a Livewire method (`$this->showDelete = true`) — **never `wire:click="$set('showDelete', true)"`**: `$set` on a property that is also Alpine-`entangle`d (which the shell does) fights the entangled value and the modal flickers open then closes. See [docs/design-system.md](../../docs/design-system.md).
