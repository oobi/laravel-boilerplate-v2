<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasOptions;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Application-wide (non-team) roles. Each carries a fixed set of
 * SystemPermission cases via defaultPermissions() — there is no per-user
 * permission override; assign a different role if different access is needed.
 *
 * Implements Filament's HasLabel/HasColor so this enum works out of the box
 * with Filament's ->badge() columns/entries (and our own <x-badge>) without
 * per-call-site formatting closures. Deliberately NOT HasIcon: this app
 * doesn't publish Filament's own CSS (@filamentStyles emits nothing here),
 * so a Filament-rendered icon has no size/color styling and renders as a
 * huge raw SVG — daisyUI classes are the only thing that render correctly.
 */
enum SystemRole: string implements HasColor, HasLabel
{
    use HasOptions;

    case SUPER_ADMIN = 'super_admin';
    case SUPPORT = 'support';

    public function getLabel(): string
    {
        return match ($this) {
            self::SUPER_ADMIN => 'Super Administrator',
            self::SUPPORT => 'Support Staff',
        };
    }

    /** Filament palette color name (also consumed directly by <x-badge>). */
    public function getColor(): string
    {
        return match ($this) {
            self::SUPER_ADMIN => 'danger',
            self::SUPPORT => 'warning',
        };
    }

    /** @return list<SystemPermission> */
    public function defaultPermissions(): array
    {
        return match ($this) {
            self::SUPER_ADMIN => SystemPermission::cases(),
            self::SUPPORT => [
                SystemPermission::ACCESS_ADMIN_PANEL,
                SystemPermission::VIEW_SYSTEM_ANALYTICS,
            ],
        };
    }
}
