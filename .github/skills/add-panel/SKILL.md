---
name: add-panel
description: 'Add a card to a Show page or a field/section to an Edit form on the admin Users pages. Use when asked to add a panel, add a show/edit card, add a form section, add a field to the edit page, or extend an admin page without editing core files.'
---

# Add an admin panel (Show card / Edit form section)

Full reference: [docs/panels.md](../../../docs/panels.md) — read it before
making changes, it covers regions, panel-triggered actions, and the one
deliberate exception (`ShowUser::userInfolist()`). This skill is the short
procedure.

## Procedure

1. Generate the class first: `php artisan bp:make:panel` (prompts for kind —
   Show card and/or Edit form section — and, for Show panels, region:
   main/sidebar). Don't hand-write a panel class from scratch.
2. Implement:
   - `ShowPanel::render(Model $subject): View` — freeform Blade/component
     markup for a Show page card.
   - `FormSection::components(Model $subject): array` — must return
     Filament schema components (`Filament\Forms\Components\*`,
     `Filament\Schemas\Components\Section`, ...) since Edit is one form.
   - A class can implement both interfaces if it needs to appear on both
     pages.
3. Override `order()`/`region()`/`visible()` from `HasPanelMetadata` only if
   the defaults (order `0`, region `Main`, always visible) don't fit.
4. Register it:
   - Core panel → add the class to `App\Support\Panels\AdminPanels::define()`
     under `PanelRegistry::for('users.show')` or `PanelRegistry::for('users.edit')`.
   - Add-on panel → call the same `PanelRegistry::for($key)->add(...)` from
     the add-on's own service provider `boot()`.
5. For an Edit form field: just add a matching column on the model —
   `EditUser::save()` persists `$this->form->getState()` as-is, no changes
   needed to the host component. `disabled()` fields are auto-excluded from
   saved state.
6. For a panel-triggered mutation (e.g. a confirm-then-act button), implement
   `HasPanelActions` and call it from the view via
   `wire:click="callPanelAction('{panelKey}', '{action}')"` — see
   `App\Panels\Users\SecurityPanel` for a working example.

## Verify

Grep `PanelRegistry::for(` to see the complete, current registration
picture and confirm no duplicate/misordered entry.
