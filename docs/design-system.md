# Design system: buttons, cards & modals

Why the app has semantic button components and a single modal shell instead of
raw daisyUI markup, and when to use each. The living, rendered reference is the
**Modals & buttons** page in the theme demo (`/admin/style-demo/modals/daisy`).
Component-authoring rules live in [.ai/rules/components.md](../.ai/rules/components.md);
colours in [.ai/rules/boost](../.ai/rules) and the theme CSS.

## Semantic buttons

Colour is a poor API: "what colour is a cancel button?" gets answered
differently on every screen, and a red button might mean "delete" on one page
and "submit" on another. So buttons are chosen by **intent**, and the intent
fixes the styling everywhere:

| Component | Use for | Looks like |
|---|---|---|
| `<x-button.action>` | The affirmative action — save, create, submit, confirm, **impersonate is not this** | Primary (solid) |
| `<x-button.cancel>` | Dismiss / abort — cancel, close, discard | Neutral, ghost |
| `<x-button.danger>` | **Destructive or irreversible** — delete, force-delete, purge, force-disable 2FA, suspend, revoke | Error / red |
| `<x-button.warning>` | **Consequential but reversible** — impersonate, grant super-admin, reset password | Warning / amber |

The line that trips people up is danger vs warning: if it **destroys data or
cuts off access**, it's `danger`; if it's **powerful but reversible and
non-destructive**, it's `warning`. Impersonation removes nothing, so it's
`warning`, not `danger` — colouring it red would falsely signal loss.

There are deliberately **no `info`/`success` buttons**. Those colours belong to
things you *read* (alerts, badges), not things you *press*; a green button as an
intent just muddies "what do I click". `<x-button>` remains the low-level
primitive the four wrap — use it directly only for a one-off colour the intents
don't cover.

### Outline buttons on a tinted panel

Outline and dash buttons are transparent at rest, so on a tinted surface (an
inset card, a soft alert) they take its colour and fade into it. Add
`surface` to fill one with `base-100` at rest:

```blade
<x-button color="neutral" variant="outline" surface>View details</x-button>
```

Only the resting state changes. Hover, focus, press and disabled keep
daisyUI's own fills, so the hover state (the button's colour with contrasting
text) still reads. Don't reach for `class="bg-base-100"` instead: a utility
background also overrides the hover fill, and the label disappears on hover.

### Links on wrapped buttons

The `<x-button.*>` wrappers declare `href` as a prop and pass it on as one.
Forwarded through `$attributes` it would be escaped twice, breaking any link
with a query string. Follow the same pattern in any new wrapper around
`<x-button>`.

## Cards

`<x-card>` is the section wrapper: a bordered `base-100` island with an
optional `title` and `actions`.

- **`inset`** recesses a card nested inside another card: no shadow, shaded
  towards `base-200`, so it reads as a well in the parent rather than a second
  floating island.
- **`accent`** takes any of the 8 semantic colours and adds a thick top edge,
  to mark status (needs attention, failed, done) without tinting the text,
  which stays in the body colour and keeps its contrast. A soft alert is for a
  short message; an accented card is for a block of content that needs
  attention, such as records to review.
- **`footer`** is a slot for the card's actions, rendered as its own row under
  a divider and padded to match the body.

```blade
<x-card accent="warning">
    <p>2 invoices need attention.</p>

    <x-slot:footer>
        <x-button.action>Review invoices</x-button.action>
    </x-slot:footer>
</x-card>
```

Demos of all three are in the style demo's component gallery.

## Modals

Every modal is built on **`<x-modal>`**, which renders a native `<dialog>`. That
choice is the whole point: the platform gives us a focus trap, Escape-to-close,
focus restoration to the trigger, an inert background, and top-layer stacking
(the modal can't be clipped by a parent's `overflow`) **for free** — so the
accessibility can't be got wrong, and there's far less JavaScript than a
hand-rolled overlay. Alpine only syncs the open state to a Livewire boolean via
`wire:model`. Visually it matches Filament's modals; it does **not** copy
Filament's `<div>`+focus-trap implementation, and it deliberately standardises
button order.

- **`<x-modal>`** — the shell. `wire:model` (open state), `variant`
  (neutral / danger / warning / info / success — sets the header icon + tint),
  `title`, `description`, optional `submit` (wraps the body in a
  `<form wire:submit>`), and a `footer` slot.
- **`<x-confirm-modal>`** — the ready-made "are you sure?": pass `wire:model`,
  `confirm` (the Livewire method) and a `variant`; it renders the icon, copy and
  a two-button footer, choosing the affirmative button from the variant
  (danger → `danger`, warning → `warning`, else `action`).
- **`<x-confirm-password-modal>`** — the "sudo" re-authentication prompt, built
  on `<x-modal>` and driven by the `ConfirmsPassword` trait.

**Opening a modal:** set its bound property directly — a trigger with
`x-on:click="$wire.showThing = true"`, or a Livewire method. Do **not** use
`wire:click="$set('showThing', true)"`: because the shell entangles that property
with Alpine, `$set` fights the entangled value and the modal flickers open then
closes.

**Button order is a house rule the shell enforces: cancel on the left, the
affirmative action on the right — never mixed.** Modal flavours (five) and
button intents (four) are intentionally not 1:1, for the same reason there are
no info/success buttons.
