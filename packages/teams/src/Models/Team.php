<?php

declare(strict_types=1);

namespace Concise\Teams\Models;

use App\Models\Role;
use App\Models\User;
use App\Support\Theme\DaisyColor;
use Concise\Teams\Database\Factories\TeamFactory;
use Concise\Teams\Enums\TeamPermission;
use Concise\Teams\Support\Roles\TeamRoleScope;
use Concise\Teams\Support\TeamContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Spatie\Permission\Guard;
use Spatie\Permission\Models\Permission;

/**
 * A team (relabelable per project — see config/teams.php). Owned, Jetstream-free:
 * the schema shape is harvested from Jetstream's proven model, but roles live in
 * spatie/laravel-permission scoped by team (never a pivot `role` column) and there
 * is no tenancy package — see ~dev/TEAMS_TIER_SCOPE.md.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property int $user_id
 * @property bool $personal_team
 * @property bool $active
 * @property array<string, mixed>|null $data
 */
class Team extends Model
{
    /** @use HasFactory<TeamFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'user_id',
        'personal_team',
        'active',
        'data',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'personal_team' => 'boolean',
            'active' => 'boolean',
            'data' => 'array',
        ];
    }

    /** Package models can't auto-resolve their factory by namespace convention. */
    protected static function newFactory(): TeamFactory
    {
        return TeamFactory::new();
    }

    /** Slug is the route key so team URLs read `/{prefix}/{slug}` (see config/teams.php). */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** Backfill a unique slug from the name when one isn't supplied explicitly. */
    protected static function booted(): void
    {
        static::creating(function (Team $team): void {
            if (blank($team->slug)) {
                $team->slug = static::uniqueSlug($team->name);
            }
        });
    }

    /** A URL-safe, collision-free slug derived from the given base string. */
    public static function uniqueSlug(string $base): string
    {
        $slug = Str::slug($base) ?: 'team';
        $candidate = $slug;
        $suffix = 2;

        while (static::withTrashed()->where('slug', $candidate)->exists()) {
            $candidate = "{$slug}-{$suffix}";
            $suffix++;
        }

        return $candidate;
    }

    /** The user who owns the team. Distinct from membership — the owner is also a member. */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Members of the team. Per-team roles are resolved via spatie, not this pivot. */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'team_user')
            ->using(Membership::class)
            ->withTimestamps();
    }

    /** Pending invitations to join this team. */
    public function invitations(): HasMany
    {
        return $this->hasMany(TeamInvitation::class);
    }

    /** @param  Builder<Team>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('active', true);
    }

    public function hasUser(User $user): bool
    {
        return $this->users()->whereKey($user->getKey())->exists()
            || $this->user_id === $user->getKey();
    }

    /** Ownership is structural (`user_id`), not a role — the team's "super admin". */
    public function isOwnedBy(User $user): bool
    {
        return $this->user_id === $user->getKey();
    }

    /**
     * Whether the member holds the given team permission, resolved in this
     * team's scope. Ownership bypasses this at the policy level (TeamPolicy::before).
     */
    public function memberHasPermission(User $user, TeamPermission $permission): bool
    {
        return app(TeamContext::class)->run($this, function () use ($user, $permission): bool {
            $user->unsetRelation('roles');

            return $user->checkPermissionTo($permission->value);
        });
    }

    /** The member's role name within this team, resolved in the team's scope. */
    public function roleFor(User $user): ?string
    {
        return app(TeamContext::class)->run($this, function () use ($user): ?string {
            $user->unsetRelation('roles');

            return $user->getRoleNames()->first();
        });
    }

    /**
     * The scope value for centrally-defined team roles. Core's Role knows only
     * its own `system` scope; the set is open, so the teams tier contributes
     * this value — nothing in core names "team".
     */
    public const ROLE_SCOPE = 'team';

    /**
     * The centrally-defined roles a member may hold — shared by every team
     * (spatie team_id NULL, so they resolve in every team's scope).
     *
     * @return Builder<Role>
     */
    public static function availableRoles(): Builder
    {
        return Role::query()->ofScope(self::ROLE_SCOPE);
    }

    /**
     * Define a shared team role holding the given permissions. Role names are
     * globally unique, so an existing role of that name (any scope) is a real
     * conflict — fail loudly rather than reuse or shadow it. Used by
     * TeamRolesSeeder, by tests as fixture setup, and by any Team Roles screen.
     *
     * @param  list<TeamPermission>  $permissions
     */
    public static function createRole(string $name, array $permissions = [], ?DaisyColor $color = null): Role
    {
        $guard = Guard::getDefaultName(Role::class);
        $existing = Role::query()->where('name', $name)->where('guard_name', $guard)->first();

        if ($existing !== null) {
            throw new RuntimeException(sprintf(
                "Cannot create team role '%s': a role with that name already exists (scope '%s') and role names are unique.",
                $name,
                $existing->scope,
            ));
        }

        $role = Role::query()->create([
            'name' => $name,
            'guard_name' => $guard,
            'color' => $color,
            ...app(TeamRoleScope::class)->attributes(),
        ]);

        if ($permissions !== []) {
            // Straight from the table, not Permission::findOrCreate(): spatie
            // answers that from a cache it flushes via model events, which are
            // off inside WithoutModelEvents seeders — a stale "missing" there
            // turns into a duplicate insert. givePermissionTo flushes explicitly.
            $role->givePermissionTo(collect($permissions)
                ->map(fn (TeamPermission $permission): Permission => Permission::query()->firstOrCreate([
                    'name' => $permission->value,
                    'guard_name' => $guard,
                ]))
                ->all());
        }

        return $role;
    }

    /**
     * Add a user to the team, optionally holding one of the shared team roles.
     * The single write path for membership (seeders, invitations, tests): the
     * pivot row and the spatie role assignment are two writes that must agree.
     *
     * @throws InvalidArgumentException when $role is not a team role
     */
    public function addMember(User $user, ?string $role = null): void
    {
        if ($role !== null && ! static::availableRoles()->where('name', $role)->exists()) {
            throw new InvalidArgumentException(sprintf("'%s' is not a team role.", $role));
        }

        $this->users()->syncWithoutDetaching([$user->getKey()]);

        if ($role !== null) {
            app(TeamContext::class)->run($this, fn () => $user->syncRoles([$role]));
        }
    }
}
