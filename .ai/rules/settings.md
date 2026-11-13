---
paths:
  - 'app/Settings/**'
  - 'database/settings/**'
---

# Settings

## A setting changes through a new, guarded settings migration
Settings classes (`app/Settings`, spatie/laravel-settings) get every property's first value from a settings migration in `database/settings`, and each add is wrapped in `if (! $this->migrator->exists('group.name'))` so an app that already has the setting (CDB uses the same `organisation` group and table migration filename) merges without a duplicate-key failure and keeps its value. Rename, add or remove a setting with a new settings migration; never edit one that has run. New settings show on the admin Settings screen through a `SettingsSection` added with `SettingsSections::add()`, never by editing `EditSettings`. Mark secrets in `encrypted()` (`SaveSettings` then leaves their values out of the audit log) and show them with `App\Support\Filament\SecretInput`, never a `TextInput`: the screen never fills an encrypted setting, so a plain input would start empty and save that over the secret, and `EditSettings` throws a LogicException when one is shown any other way. See docs/boilerplate/settings.md.

## Read the display time zone through DisplayTimezone, never config
The zone times are shown in is `OrganisationSettings::$timezone`, resolved by `App\Support\DisplayTimezone::name()` (and Filament's `FilamentTimezone`, set from it). `config('app.display_timezone')` / `APP_DISPLAY_TIMEZONE` only seeds the first value when the settings migration runs, so reading it directly shows the wrong zone after an admin changes it. Never read a settings class while booting (a provider's `register()`/`boot()` body, a config file): the settings table doesn't exist during install and the first migrate. Pass a closure that reads it when needed, as `DisplayTimezone::resolveUsing()` does. In tests, `OrganisationSettings::fake(['timezone' => ...])`.
