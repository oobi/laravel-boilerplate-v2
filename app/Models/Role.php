<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Theme\DaisyColor;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Extends Spatie's Role model with an admin-configurable badge color (see
 * `HasPermissionsSchema`'s sibling role forms) and a `scope`. Core knows only
 * its own scope, `system` (the app-wide Roles screen); the set of scopes is
 * open — an add-on contributes further values (e.g. the teams tier's `team`)
 * as plain strings, which is why this is a string and not a closed enum. See
 * config/permission.php `models.role`, which must point here for route model
 * binding and relations to resolve this class instead of Spatie's base one.
 *
 * @property DaisyColor|null $color
 * @property string $scope
 */
class Role extends SpatieRole
{
    /** The scope core manages itself: the app-wide roles in the Roles screen. */
    public const SYSTEM_SCOPE = 'system';

    /**
     * Default to the system scope in memory as well as in the DB — a freshly
     * created model doesn't reflect the column default back, so without this
     * `$role->scope` would be null until re-fetched.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'scope' => self::SYSTEM_SCOPE,
    ];

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

    /**
     * App-wide roles managed in the system Roles screen.
     *
     * @param  Builder<Role>  $query
     */
    public function scopeSystemRoles(Builder $query): void
    {
        $query->where('scope', self::SYSTEM_SCOPE);
    }
}
