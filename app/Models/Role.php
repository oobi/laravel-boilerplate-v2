<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Theme\DaisyColor;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Extends Spatie's Role model with an admin-configurable badge color (see
 * `HasPermissionsSchema`'s sibling role forms) — see config/permission.php
 * `models.role`, which must point here for route model binding and
 * relations to resolve this class instead of Spatie's base one.
 *
 * @property DaisyColor|null $color
 */
class Role extends SpatieRole
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'color' => DaisyColor::class,
        ];
    }

    /** Falls back to neutral for roles created before the color field existed. */
    public function badgeColor(): DaisyColor
    {
        return $this->color ?? DaisyColor::NEUTRAL;
    }
}
