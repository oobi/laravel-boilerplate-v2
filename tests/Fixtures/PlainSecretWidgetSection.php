<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Filament\Forms\Components\TextInput;

/** A section that shows an encrypted setting in a plain input, which the Settings screen refuses. */
class PlainSecretWidgetSection extends WidgetSettingsSection
{
    public function components(): array
    {
        return [
            TextInput::make('greeting')->label('Widget greeting'),
            TextInput::make('api_key')->label('Widget key')->password(),
        ];
    }
}
