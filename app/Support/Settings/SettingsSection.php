<?php

declare(strict_types=1);

namespace App\Support\Settings;

use Filament\Schemas\Components\Component;
use Illuminate\Contracts\Auth\Authenticatable;
use Spatie\LaravelSettings\Settings;

/**
 * One section of the admin Settings screen: the fields for one settings class.
 * Each field is named after a property of that class; the screen fills the
 * fields from it and SaveSettings writes them back, so a section never saves
 * anything itself. Registered with SettingsSections::add().
 */
interface SettingsSection
{
    /** Where the section's fields sit in the form's state; unique per screen. */
    public function key(): string;

    public function order(): int;

    /** @return class-string<Settings> */
    public function settings(): string;

    /** Whether the viewer sees and saves this section. Anyone on the screen can manage settings; narrow it further here. */
    public function visible(Authenticatable $viewer): bool;

    /** @return array<int, Component> */
    public function components(): array;
}
