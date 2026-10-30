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

## After a completed action on a component with no form of its own, assert assertHasNoErrors()
Since Filament 5.7.7 a closed action forgets its cached schema (forgetCachedMountedActionSchemas()). On a component that declares no schema itself (e.g. ManageOwnership, Onboarding, FilamentModalGallery), assertHasNoFormErrors() after a finished callAction()/callMountedAction() then has no schema to resolve and throws a TypeError. Use Livewire's assertHasNoErrors() there: with no keys, assertHasNoFormErrors() only calls assertHasNoErrors([]) over the whole error bag (the schema just prefixes keys), so the check is identical. Keep assertHasNoFormErrors()/assertHasFormErrors() while the action is still mounted, or on components with their own form.

## Livewire tests: a missing model from a call is a 404 response, not an exception
Since Livewire 4.4.6 a ModelNotFoundException thrown during ->call()/->mountAction() becomes the test's 404 response, as AuthorizationException and HttpException already did. Assert ->assertNotFound() on the chain; expectException(ModelNotFoundException::class) no longer fires there. Direct calls outside Livewire (an Action's handle()) still throw.

## Assert on our own markup, not a common word
Filament renders its own labels into the page (the 5.10 select's config carries "Clear selection"), so assertSee('Clear') can match Filament's text rather than ours. Assert on markup only our view produces, such as its own `wire:click` or `id`, with assertSeeHtml().

## Core tests never depend on the teams tier
Every test outside tests/Feature/Teams/ must pass with the teams tier removed: bp:remove-teams deletes only tests/Feature/Teams/** (GitHub #22). No Concise\Teams import, team_route()/team_trans(), team route name (route('team.'/'teams.') and the other route helpers) or TeamSystemPermission case (the tier's registered system permissions, deleted with it) in a core test, and no reliance on the tier's login resolver: assert Destination::home($user), or LoginRedirectRegistry::flush() then register a stand-in resolver. A case that needs the tier goes in tests/Feature/Teams/ (e.g. TeamLandingTest, TeamPageTitleTest). CoreTestsStandAloneTest enforces the static part.

## Test authorization on Livewire updates through the update route, not Livewire::test()
Livewire::test() never goes through Livewire's update route, so it skips the persistent middleware (route `can:`, auth and the app's registered ones) that guards a page's later requests. To test that access lost with a page open is refused at the next click, render the page with `$this->get(route(...))`, change the access, then call `$this->livewireUpdate($page)` (tests/TestCase.php) and assert 403. See docs/boilerplate/permissions.md, "Access lost while a page is open".
