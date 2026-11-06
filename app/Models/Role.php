<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Support\Roles\RoleScopeRegistry;
use App\Support\Theme\DaisyColor;
use App\Support\TwoFactor\GracePeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role as SpatieRole;
use Spatie\Permission\PermissionRegistrar;

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
    use Auditable {
        Auditable::delete as auditedDelete;
    }

    /** The scope core manages itself: the app-wide roles in the Roles screen. */
    public const SYSTEM_SCOPE = 'system';

    /** @var array{holder_count?: int, permissions?: list<string>} */
    private array $accessLostOnDelete = [];

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
        static::saved(function (Role $role): void {
            self::forgetCaches();

            if ($role->wasChanged('requires_two_factor') && ! $role->requires_two_factor) {
                $role->clearMembersGracePeriods($role->users()->pluck('id')->all());
            }
        });
        static::deleted(fn () => self::forgetCaches());
    }

    /**
     * Now, for the rest of this write, and again once it commits: a role write sits
     * in a transaction, and another request could otherwise re-cache the old
     * answer before the commit and keep a revoked permission alive for a day.
     */
    private static function forgetCaches(): void
    {
        GracePeriod::forgetRequiredRoleNames();

        DB::afterCommit(function (): void {
            GracePeriod::forgetRequiredRoleNames();
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        });
    }

    /** What spatie calls on a grant or revoke, which may be in a transaction with no role save to clear after it. */
    public function forgetCachedPermissions(): void
    {
        parent::forgetCachedPermissions();

        DB::afterCommit(fn () => app(PermissionRegistrar::class)->forgetCachedPermissions());
    }

    /** Members are noted first: spatie's own deleting hook detaches them before any of ours runs. */
    public function delete(): ?bool
    {
        $members = $this->users()->pluck('id')->all();

        // A count, as a role can have thousands of holders.
        $this->accessLostOnDelete = [
            'holder_count' => RoleScopeRegistry::find($this->scope)?->holderCount($this) ?? count($members),
            'permissions' => $this->permissions()->pluck('name')->sort()->values()->all(),
        ];

        return tap($this->auditedDelete(), function (?bool $deleted) use ($members): void {
            if ($deleted) {
                $this->clearMembersGracePeriods($members);
            }
        });
    }

    /** spatie's hook for adding to an entry: `role.deleted` also says what access went with the role. */
    public function beforeActivityLogged(Model $activity, string $event): void
    {
        if ($event === 'role.deleted') {
            $activity->setAttribute('properties', collect($activity->getAttribute('properties'))->merge($this->accessLostOnDelete));
        }
    }

    /**
     * A member's mandate may have ended with this role's (#40).
     *
     * @param  list<int>  $userIds
     */
    private function clearMembersGracePeriods(array $userIds): void
    {
        User::query()->whereIn('id', $userIds)->whereNotNull('two_factor_grace_started_at')->get()
            ->each(fn (User $user) => GracePeriod::clearIfNotRequired($user));
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
