<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use App\Support\Settings\SettingsSection;
use Filament\Forms\Components\TextInput;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * A second section on WidgetSettings, which a test can hide: the note
 * WidgetSettingsSection leaves out, and a setting whose name is on the audit
 * log's redact list.
 */
class WidgetNoteSection implements SettingsSection
{
    public static bool $visible = true;

    public function key(): string
    {
        return 'widget_notes';
    }

    public function order(): int
    {
        return 30;
    }

    public function settings(): string
    {
        return WidgetSettings::class;
    }

    public function visible(Authenticatable $viewer): bool
    {
        return self::$visible;
    }

    public function components(): array
    {
        return [
            TextInput::make('internal_note')->label('Internal note'),
            TextInput::make('daily_token_allowance')->label('Daily token allowance')->integer(),
        ];
    }
}
