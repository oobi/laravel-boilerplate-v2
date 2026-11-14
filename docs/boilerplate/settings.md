# Settings

Choices that belong to the organisation running the app, made on a screen
instead of in `.env`, so changing one needs no deploy. Admin > System >
Settings, behind the `manage settings` permission.

Core ships one setting, the **time zone** people read times in. An app or
add-on adds its own sections to the same screen from its provider, without
editing the screen.

Built on [spatie/laravel-settings](https://github.com/spatie/laravel-settings):
each group of settings is a typed class, stored one row per setting in the
`settings` table.

## The time zone

`App\Settings\OrganisationSettings::$timezone`. Times are stored in UTC; this
is only the zone they are shown in.

- `App\Support\DisplayTimezone` reads it, through a resolver
  `AppServiceProvider` sets with `DisplayTimezone::resolveUsing()`. The
  resolver is a closure asked each time a time is shown, so booting,
  installing and migrating never read the settings table.
- Filament's own date columns and pickers follow it too
  (`FilamentTimezone::set()`, the same closure).
- Its first value comes from `APP_DISPLAY_TIMEZONE`, falling back to
  `app.timezone` (UTC), when `php artisan migrate` runs the settings migration.
  After that the Settings screen owns it; changing the env value does nothing.
- The field lists every zone PHP knows; Filament refuses anything else. A
  stored older alias ("US/Eastern") stays selectable.

A tier that wants a different zone in some places (a team's own zone) wraps
the resolver from its provider, falling back to the previous one:

```php
$organisation = DisplayTimezone::resolver();

DisplayTimezone::resolveUsing(fn (): ?string => current_team()?->timezone ?? $organisation());
```

## Who can change settings

`SystemPermission::MANAGE_SETTINGS` (`manage settings`), in the "Settings"
category on the Roles screen. It implies `access admin panel`. On an existing
install, deploy with `php artisan db:seed --class=PermissionSeeder` to create
it; until a role is given it on the Roles screen only super admins see
Settings. The route
carries `can:manage settings`, so someone who loses it with the screen open is
refused at their next click; the screen and `SaveSettings` check it again.

A section can narrow it further: `visible()` false hides the section and
`SaveSettings` skips it, whatever is posted.

## Saving and the audit log

`App\Actions\Settings\SaveSettings` writes each visible section's values to
its settings class: only through the fields that section defines, and never a
locked setting (spatie's `lock()`). Whatever else is passed is ignored, so a
command or tool calling the action directly can't reach a setting through a
section with no field for it, even when two sections share one settings class
and only one is visible to that person.

**Access is the section's `visible()` and `lock()`, never a field's
condition.** A field hidden or disabled by a condition still counts as the
section's field when the action is called directly: judging the condition needs
the screen's whole state. To keep a setting from some people, give it its own
section with a narrower `visible()`; to keep it from everyone, lock it. The
values aren't validated in the action either: the screen validates them, and a
direct caller must pass valid ones.

It records one `settings.updated` entry (System category) when anything
changed, inside the same transaction, with `Audit::recordChanges()`: the
detail page's Changes table shows each setting under its field's label, before
and after. **A secret** (encrypted with `encrypted()` on the class, or shown
with a `SecretInput`) is listed with its values as `[redacted]`. Nothing else
is redacted: settings are defined in code and their secrets are marked there,
so the audit log's name list (`*token*` and the rest) would only hide ordinary
values. Entries written before this kept `changed`, `before` and `after` in
their properties, and still show them as event data.

A save that changes nothing records nothing, and the screen says "Nothing
changed".

## Adding a section

1. **A settings class** under `app/Settings` (an add-on: its own namespace):

   ```php
   class InvoiceSettings extends Settings
   {
       public string $prefix;

       public ?string $api_key;

       public static function group(): string
       {
           return 'invoices';
       }

       /** Kept out of the audit log's values. */
       public static function encrypted(): array
       {
           return ['api_key'];
       }
   }
   ```

   List it in `config/settings.php` (`settings`), so it resolves once per
   request. An add-on binds it from its provider's `register()` instead:
   `$this->app->scoped(InvoiceSettings::class)`.

2. **A settings migration** in `database/settings` giving every property its
   first value. Guard each add, so an app that already has the setting keeps
   its value:

   ```php
   return new class extends SettingsMigration
   {
       public function up(): void
       {
           if (! $this->migrator->exists('invoices.prefix')) {
               $this->migrator->add('invoices.prefix', 'INV-');
           }

           if (! $this->migrator->exists('invoices.api_key')) {
               $this->migrator->addEncrypted('invoices.api_key', null);
           }
       }
   };
   ```

   `php artisan migrate` runs these with the other migrations. Change a
   setting later with a new settings migration, never by editing one that has
   run.

3. **A section** implementing `App\Support\Settings\SettingsSection`: a `key()`
   (where its fields sit in the form; use the group), `order()` (core's
   Organisation section is 10), `settings()` (the class), `visible()` and
   `components()`. Name each field after a property of the class; the screen
   fills them from it and `SaveSettings` writes them back. Put the fields in a
   Filament `Section` with a heading, as core's does. Give fields plain labels:
   `SaveSettings` reads them for the audit log outside the screen, where a label
   built from the form's state or the signed-in user has nothing to go on.

   - The screen fills only the fields a section shows, and never an encrypted
     setting. Show a secret with `App\Support\Filament\SecretInput` (see
     `docs/boilerplate/design-system.md`, "Secret fields"): left alone it isn't
     saved, so the stored secret stays; a new value replaces it; saved empty
     or removed, it's null, so make the property nullable. Any other field on
     an encrypted setting throws a LogicException when the screen opens: it
     would start empty and save that over the secret.
   - A field's value must already be the property's type (cast it with
     `->dehydrateStateUsing()`, e.g. `(int)` for an id from a select): it's
     compared and stored as given, so a string for an `int` would read as a
     change on every save.
   - A locked setting (spatie's `lock()`) keeps its value whatever is saved.
     Show it as such: `->disabled(fn (): bool => app(InvoiceSettings::class)->isLocked('prefix'))`.

   ```php
   class InvoiceSettingsSection implements SettingsSection
   {
       public function key(): string { return 'invoices'; }
       public function order(): int { return 20; }
       public function settings(): string { return InvoiceSettings::class; }
       public function visible(Authenticatable $viewer): bool { return true; }

       public function components(): array
       {
           return [
               Section::make(__('invoices.settings'))->schema([
                   TextInput::make('prefix')->label(__('invoices.prefix'))->required()->maxLength(10),
                   SecretInput::make('api_key')
                       ->label(__('invoices.api_key'))
                       ->noun(__('invoices.key'))
                       ->stored(fn (): bool => filled(app(InvoiceSettings::class)->api_key)),
               ]),
           ];
       }
   }
   ```

4. **Register it.** Core: `SettingsSections::add()` in
   `App\Support\Panels\AdminPanels::define()`, with core's other sections. An
   add-on: the same call from its provider's `boot()`. To see every section,
   grep `SettingsSections::add(`.

Tests: `SettingsSections::flush()` runs before each test (`tests/TestCase.php`);
`tests/Feature/Admin/Settings/EditSettingsTest.php` registers a fixture section
(`tests/Fixtures/WidgetSettingsSection.php`) and adds its settings in the test.
`OrganisationSettings::fake([...])` sets a value without the database.

## Merging into an app that already has settings

The names match the pattern apps built before this already used
(CDB): the `settings` table migration (`2026_10_24_000000_create_settings_table.php`),
`App\Settings\OrganisationSettings` in group `organisation`, the
`manage settings` permission, the `settings.edit` route and the `settings` nav
item. On a merge:

- Files the app already has (the table migration, its settings class, its
  screen) resolve to the app's version; core's settings migration finds the
  time zone already there and leaves it.
- Role grants of `manage settings` carry over unchanged.
- An app whose screen doesn't use `SettingsSections` won't show sections core
  or add-ons register later; move its sections onto the registry to get them.
  Until then core's `tests/Feature/Admin/Settings/EditSettingsTest.php` fails
  there (it expects the registry and `SaveSettings`' Changes table):
  keep the app's own tests for its screen and drop or adapt core's.
