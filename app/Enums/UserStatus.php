<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Derived user status (not a stored column): combines the `active` flag with
 * email verification. See ListUsers::statusFor() for the derivation, shared
 * with ShowUser's infolist so both screens agree.
 *
 * Implements Filament's HasLabel/HasColor so this enum works out of the box
 * with Filament's ->badge() columns/entries (and our own <x-badge>) without
 * per-call-site formatting closures.
 */
enum UserStatus: string implements HasColor, HasLabel
{
    case ACTIVE = 'active';
    case PENDING = 'pending';
    case INACTIVE = 'inactive';

    public function getLabel(): string
    {
        return match ($this) {
            self::ACTIVE => __('admin.active'),
            self::PENDING => __('admin.pending'),
            self::INACTIVE => __('admin.inactive'),
        };
    }

    /** Filament palette color name (also consumed directly by <x-badge>). */
    public function getColor(): string
    {
        return match ($this) {
            self::ACTIVE => 'success',
            self::PENDING => 'info',
            self::INACTIVE => 'warning',
        };
    }

    /** @return array<string, string> value => label, for select/dropdown options. */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $status) {
            $options[$status->value] = $status->getLabel();
        }

        return $options;
    }
}
