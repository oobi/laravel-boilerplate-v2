<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * The organisation's settings and their first values. `APP_DISPLAY_TIMEZONE`
 * only seeds the time zone; after this, the Settings screen owns it. Each add
 * is guarded so an app that already has the setting (CDB) keeps its value.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        if (! $this->migrator->exists('organisation.timezone')) {
            $this->migrator->add('organisation.timezone', config('app.display_timezone') ?: config('app.timezone'));
        }
    }
};
