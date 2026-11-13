<?php

declare(strict_types=1);

namespace App\Panels\Settings;

use App\Settings\OrganisationSettings;
use App\Support\Settings\SettingsSection;
use DateTimeZone;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Illuminate\Contracts\Auth\Authenticatable;

/** The organisation's own choices: the time zone people read times in. */
class OrganisationSection implements SettingsSection
{
    public function key(): string
    {
        return 'organisation';
    }

    public function order(): int
    {
        return 10;
    }

    public function settings(): string
    {
        return OrganisationSettings::class;
    }

    public function visible(Authenticatable $viewer): bool
    {
        return true;
    }

    public function components(): array
    {
        return [
            Section::make(__('settings.organisation'))
                ->schema([
                    Select::make('timezone')
                        ->label(__('settings.timezone'))
                        ->helperText(__('settings.timezone_hint'))
                        ->options(fn (): array => self::zones())
                        ->searchable()
                        ->selectablePlaceholder(false)
                        ->required(),
                ]),
        ];
    }

    /**
     * Every zone by its identifier. Filament refuses anything not in the list.
     *
     * @return array<string, string>
     */
    private static function zones(): array
    {
        return collect(DateTimeZone::listIdentifiers())
            ->mapWithKeys(fn (string $zone): array => [$zone => str_replace('_', ' ', $zone)])
            ->all();
    }
}
