---
paths:
  - 'tests/Feature/**'
---

# Feature

## Livewire component test style
Test Livewire components via `Livewire::test()` / `Livewire::actingAs()->test()`, chaining `->set()`/`->call()` with Livewire assertions (`assertSet`, `assertSee`, `assertOk`) and Filament's action helpers where applicable: `callAction()` / `mountAction()` / `assertActionHidden()` with `TestAction::make('name')->table($record)` for a table row action. The table-action aliases (`callTableAction`, `assertTableActionHidden`) are deprecated in Filament 5; see tests.md.
