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
A mounted Filament action's modal content is not in the Livewire test HTML, so assertSee() on modal text fails even when it renders in the browser. Assert through the mounted schema instead: assertSchemaComponentVisible/Hidden('key', 'mountedActionSchema0'), assertFormFieldExists('field', 'mountedActionSchema0', fn ($field) => ...), assertHasFormErrors(['field']); a field's helper text is its child schema Field::BELOW_CONTENT_SCHEMA_KEY. Errors in an action's form are assertHasFormErrors()/assertHasNoFormErrors(), as Filament 5's docs use; assertHas(No)?(Table)?ActionErrors are Filament 3 aliases, flagged deprecated. Likewise the table-action helpers (callTableAction, mountTableAction, assertTableAction*): use callAction/mountAction/assertAction* with TestAction::make('name')->table($record); vendor/filament/*/.stubs.php lists what's deprecated. Check the installed testing helpers (vendor/filament/*/src/Testing) before writing the assertion, and run only the failing test until it passes before the full suite.

## Run the suite in parallel, never serially
While iterating, run only what you touched: `php artisan test --compact <file>` or `--filter=<method>`. Before committing: `composer test:fast` (the pre-commit hook runs it), the parallel suite minus the `slow` group. Before pushing or merging: `php artisan test --parallel --compact` (or `composer test`, the same run with a compact failure summary). Never a bare serial `php artisan test` over the whole suite: it is several times slower than the parallel run.

## The slow group is a budget
A test class that takes over a second gets `#[Group('slow')]` and a docblock line saying why, which keeps it out of `test:fast`. No test class qualifies today. Fix a slow test before adding it to the group; check with `php artisan test --profile`.
