<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use App\Support\Filament\SecretInput;
use App\Support\Settings\SettingsSection;
use Filament\Forms\Components\TextInput;
use Illuminate\Contracts\Auth\Authenticatable;

/** An add-on's section of the Settings screen, which a test can hide. */
class WidgetSettingsSection implements SettingsSection
{
    public static bool $visible = true;

    public function key(): string
    {
        return 'widgets';
    }

    public function order(): int
    {
        return 20;
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
            TextInput::make('greeting')->label('Widget greeting')->required(),
            SecretInput::make('api_key')->label('Widget key')->noun('key')->stored(fn (): bool => filled(app(WidgetSettings::class)->api_key)),
            SecretInput::make('webhook_secret')
                ->label('Webhook secret')
                ->required()
                ->rule('max:20')
                ->stored(fn (): bool => filled(app(WidgetSettings::class)->webhook_secret)),
        ];
    }
}
