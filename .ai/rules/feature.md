---
paths:
  - 'tests/Feature/**'
---

# Feature

## Livewire component test style
Test Livewire components via `Livewire::test()` / `Livewire::actingAs()->test()`, chaining `->set()`/`->call()` with Livewire assertions (`assertSet`, `assertSee`, `assertOk`) and Filament table helpers (`callTableAction`, `assertTableActionHidden`) where applicable.
