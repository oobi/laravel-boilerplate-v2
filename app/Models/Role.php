<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Theme\DaisyColor;
use App\Support\TwoFactor\GracePeriod;
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
 * @property bool $requires_two_factor
 */
class Role extends SpatieRole
{
    /** The scope core manages itself: the app-wide roles in the Roles screen. */
    public const SYSTEM_SCOPE = 'system';

    /**
     * Default to the system scope (and no 2FA mandate) in memory as well as
     * in the DB. A freshly created model doesn't reflect the column defaults
     * back, so without this `$role->scope` would be null until re-fetched.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'scope' => self::SYSTEM_SCOPE,
        'requires_two_factor' => false,
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'color' => DaisyColor::class,
            'requires_two_factor' => 'boolean',
        ];
    }

    /**
     * GracePeriod caches which role names require 2FA; any role write can
     * change that set (flag toggled, role renamed or deleted), so drop it.
     */
    protected static function booted(): void
    {
        static::saved(fn () => GracePeriod::forgetRequiredRoleNames());
        static::deleted(fn () => GracePeriod::forgetRequiredRoleNames());
    }

    /** Falls back to neutral for roles created before the color field existed. */
    public function badgeColor(): DaisyColor
    {
        return $this->color ?? DaisyColor::NEUTRAL;
    }

    /**
     * Roles in one scope — see App\Support\Roles\RoleScope for what a scope is.
     *
     * @param  Builder<Role>  $query
     */
    public function scopeOfScope(Builder $query, string $scope): void
    {
        $query->where('scope', $scope);
    }

    /**
     * App-wide roles managed in the system Roles screen.
     *
     * @param  Builder<Role>  $query
     */
    public function scopeSystemRoles(Builder $query): void
    {
        $query->ofScope(self::SYSTEM_SCOPE);
    }

    /**
     * System roles whose members must set up two-factor authentication.
     *
     * @param  Builder<Role>  $query
     */
    public function scopeRequiringTwoFactor(Builder $query): void
    {
        $query->systemRoles()->where('requires_two_factor', true);
    }
}
