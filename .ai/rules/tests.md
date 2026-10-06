---
paths:
  - 'tests/**'
---

# Tests

## Tests use RefreshDatabase
Feature and Unit tests use the `RefreshDatabase` trait for database isolation between runs.

## Factories with named states, no seeders in tests
Build test fixtures with model factories and their custom state methods (e.g. `User::factory()->superAdmin()->unverified()`) — don't call `$this->seed()` in tests.

## Testing Filament action modals: assert the schema, not the HTML
A mounted Filament action's modal content is not in the Livewire test HTML, so assertSee() on modal text fails even when it renders in the browser. Assert through the mounted schema instead: assertSchemaComponentVisible/Hidden('key', 'mountedActionSchema0'), assertFormFieldExists('field', 'mountedActionSchema0', fn ($field) => ...), assertHasActionErrors(['field']); a field's helper text is its child schema Field::BELOW_CONTENT_SCHEMA_KEY. Check the installed testing helpers (vendor/filament/*/src/Testing) before writing the assertion, and run only the failing test until it passes before the full suite.
