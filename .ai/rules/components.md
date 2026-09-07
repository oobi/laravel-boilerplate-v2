---
paths:
  - 'resources/views/components/**'
---

# Components

## Blade UI components are anonymous, not class-based
UI components are anonymous Blade components using `@props` — do not add class-based components under `app/View/Components`.

## Buttons: reach for the semantic component, not a colour
Prefer the four intent components over `<x-button color="…">` in app markup — the intent, not the colour, is the API, and it keeps every instance identical. See [docs/design-system.md](../../docs/design-system.md).

- `<x-button.action>` — affirmative do/add/save/submit/confirm (primary). The default.
- `<x-button.cancel>` — dismiss/abort (neutral ghost). Defaults its label to "Cancel".
- `<x-button.danger>` — destructive or irreversible: delete, force-delete, purge, force-disable 2FA, suspend, revoke. **Not just "delete".**
- `<x-button.warning>` — consequential but *reversible/non-destructive*: impersonate, grant super-admin, reset password.

Rubric for danger vs warning: destroys/removes/cuts off access → `danger`; powerful but reversible → `warning`; otherwise → `action`. `<x-button>` stays the low-level primitive these wrap; use it directly only for a colour the four intents don't cover. There are no `info`/`success` buttons by design — those colours are for status surfaces (alerts, badges), not actions.

## Modals: use the shell, never hand-roll a `<dialog>` / daisyUI `.modal`
Build every modal on `<x-modal>` (native `<dialog>` → focus-trap, Esc, focus-restore and inert background come free). It requires a `wire:model` boolean and takes `variant` (neutral | danger | warning | info | success), `title`, `description`, optional `submit` (wraps the body in a `<form wire:submit>`), and a `footer` slot. Footer buttons go **cancel-left, action-right** — the shell enforces this and `<x-confirm-modal>` picks the affirmative button from the variant. For an "are you sure?", prefer `<x-confirm-modal wire:model confirm="method" variant …>`. Dismiss from markup with the Alpine `open = false`.

Open a modal by setting its bound property, e.g. a trigger `x-on:click="$wire.showDelete = true"`, or a Livewire method (`$this->showDelete = true`) — **never `wire:click="$set('showDelete', true)"`**: `$set` on a property that is also Alpine-`entangle`d (which the shell does) fights the entangled value and the modal flickers open then closes. See [docs/design-system.md](../../docs/design-system.md).
