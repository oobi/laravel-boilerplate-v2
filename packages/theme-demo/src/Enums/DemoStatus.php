<?php

declare(strict_types=1);

namespace Concise\ThemeDemo\Enums;

use App\Support\Theme\DaisyColor;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Mirrors App\Enums\UserStatus's HasColor/HasLabel shape so <x-badge> "just works" here too. */
enum DemoStatus: string implements HasColor, HasLabel
{
    case ACTIVE = 'active';
    case PENDING = 'pending';
    case ARCHIVED = 'archived';

    public function getLabel(): string
    {
        return match ($this) {
            self::ACTIVE => 'Active',
            self::PENDING => 'Pending',
            self::ARCHIVED => 'Archived',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::ACTIVE => DaisyColor::SUCCESS->toFilamentColor(),
            self::PENDING => DaisyColor::INFO->toFilamentColor(),
            self::ARCHIVED => DaisyColor::WARNING->toFilamentColor(),
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case): array => [$case->value => $case->getLabel()])->all();
    }
}
