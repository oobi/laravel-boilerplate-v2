<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasOptions;
use App\Support\Theme\DaisyColor;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Derived user status (not a stored column): combines the `active` flag with
 * email verification. See User::getStatusAttribute() for the derivation,
 * shared by ListUsers' table and ShowUser's infolist so both agree.
 *
 * Implements Filament's HasLabel/HasColor so this enum works out of the box
 * with Filament's ->badge() columns/entries (and our own <x-badge>) without
 * per-call-site formatting closures.
 */
enum UserStatus: string implements HasColor, HasLabel
{
    use HasOptions;

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
            self::ACTIVE => DaisyColor::SUCCESS->toFilamentColor(),
            self::PENDING => DaisyColor::INFO->toFilamentColor(),
            self::INACTIVE => DaisyColor::WARNING->toFilamentColor(),
        };
    }
}
