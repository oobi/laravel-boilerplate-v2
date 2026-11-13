<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/** Choices that belong to the organisation running the app, set on the admin Settings screen. */
class OrganisationSettings extends Settings
{
    /** The zone people read times in (App\Support\DisplayTimezone). Times are stored in UTC either way. */
    public string $timezone;

    public static function group(): string
    {
        return 'organisation';
    }
}
