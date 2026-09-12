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
use Concise\Teams\Support\TeamLabels;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
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

    /**
     * Backfill a unique slug from the name when one isn't supplied explicitly;
     * and on a hard delete, purge the team-scoped role assignments — spatie's
     * pivot has no FK to teams, so nothing cascades them (memberships and
     * invitations do cascade at the DB).
     */
    protected static function booted(): void
    {
        static::creating(function (Team $team): void {
            if (blank($team->slug)) {
                $team->slug = static::uniqueSlug($team->name);
            }
        });

        static::forceDeleting(function (Team $team): void {
            DB::table(config('permission.table_names.model_has_roles'))
                ->where(config('permission.column_names.team_foreign_key', 'team_id'), $team->getKey())
                ->delete();
        });
    }

    /**
     * The delete confirmation's text, with the member count front and centre —
     * a number changes how carefully a confirmation gets read.
     */
    public static function deleteWarning(Team $team, bool $permanent = false): string
    {
        $count = $team->users()->count();

        return team_trans_choice(
            $permanent ? 'admin.force_delete_warning' : 'admin.delete_warning',
            $count,
            ['name' => $team->name, 'count' => $count],
        );
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
            ->withPivot('is_owner', 'suspended_at')
            ->withTimestamps();
    }

    /** Pending invitations to join this team. */
    public function invitations(): HasMany
    {
        return $this->hasMany(TeamInvitation::class);
    }

    /** Custom domains for this team (the optional overlay, 5h). */
    public function domains(): HasMany
    {
        return $this->hasMany(Domain::class);
    }

    /** The team's verified primary domain, if it has one. */
    public function primaryDomain(): ?Domain
    {
        return $this->domains()
            ->whereNotNull('verified_at')
            ->where('is_primary', true)
            ->first();
    }

    /** @param  Builder<Team>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('active', true);
    }

    /** A member, suspended or not (the owner always is). For access, see isActiveMember(). */
    public function hasUser(User $user): bool
    {
        return $this->users()->whereKey($user->getKey())->exists()
            || $this->user_id === $user->getKey();
    }

    /** Suspended by a team admin: still a member, but without access until reinstated. */
    public function isSuspended(User $user): bool
    {
        return $this->users()
            ->whereKey($user->getKey())
            ->wherePivotNotNull('suspended_at')
            ->exists();
    }

    /** A member who may actually use the team: not suspended (the primary owner never is). */
    public function isActiveMember(User $user): bool
    {
        return $this->isPrimaryOwner($user)
            || $this->users()->whereKey($user->getKey())->wherePivotNull('suspended_at')->exists();
    }

    /**
     * Withhold a member's access to the team without removing them: membership,
     * role and ownership flag are all kept for reinstateMember(). The primary
     * owner can't be suspended (transfer ownership first).
     */
    public function suspendMember(User $user): void
    {
        if ($this->isPrimaryOwner($user)) {
            throw new InvalidArgumentException('The primary owner cannot be suspended — transfer ownership first.');
        }

        if (! $this->hasUser($user)) {
            throw new InvalidArgumentException(sprintf('%s is not a member of %s.', $user->email, $this->name));
        }

        $this->users()->updateExistingPivot($user->getKey(), ['suspended_at' => now()]);
    }

    public function reinstateMember(User $user): void
    {
        $this->users()->updateExistingPivot($user->getKey(), ['suspended_at' => null]);
    }

    /**
     * Ownership is structural, not a role — the team's "super admin" (Slack
     * model). The PRIMARY owner is `user_id`: one account, transferable, and
     * the only one that may transfer ownership, manage co-owners or delete the
     * team. CO-OWNERS (`team_user.is_owner`) share the permission bypass so
     * day-to-day owner work never waits on one person, and can be demoted by
     * the primary owner.
     */
    public function isPrimaryOwner(User $user): bool
    {
        return $this->user_id === $user->getKey();
    }

    /** Primary owner or co-owner: anyone who bypasses the team's permission checks. */
    public function isOwnedBy(User $user): bool
    {
        return $this->isPrimaryOwner($user) || $this->ownerIds()->contains($user->getKey());
    }

    /**
     * A co-owner whose shield is currently active: an owner who is neither the
     * primary owner nor suspended. (Suspension pauses the shield — the owner flag
     * stays, but the protection doesn't apply while suspended.)
     */
    public function isCoOwner(User $user): bool
    {
        return $this->isOwnedBy($user) && ! $this->isPrimaryOwner($user) && ! $this->isSuspended($user);
    }

    /**
     * Every owner's user id, primary first.
     *
     * @return SupportCollection<int, int>
     */
    public function ownerIds(): SupportCollection
    {
        return $this->users()
            ->wherePivot('is_owner', true)
            ->pluck('users.id')
            ->prepend($this->user_id)
            ->unique()
            ->values();
    }

    /** Promote a member to co-owner. */
    public function makeOwner(User $user): void
    {
        if (! $this->hasUser($user)) {
            throw new InvalidArgumentException(sprintf('%s is not a member of %s.', $user->email, $this->name));
        }

        if ($this->isPrimaryOwner($user)) {
            return;
        }

        $this->users()->updateExistingPivot($user->getKey(), ['is_owner' => true]);
    }

    /** Demote a co-owner to an ordinary member (their team role, if any, is untouched). */
    public function revokeOwner(User $user): void
    {
        if ($this->isPrimaryOwner($user)) {
            throw new InvalidArgumentException('The primary owner cannot be demoted — transfer ownership first.');
        }

        $this->users()->updateExistingPivot($user->getKey(), ['is_owner' => false]);
    }

    /** Hand primary ownership to a member. The previous primary owner stays on as a co-owner. */
    public function transferOwnership(User $to): void
    {
        if (! $this->hasUser($to)) {
            throw new InvalidArgumentException(sprintf('%s is not a member of %s.', $to->email, $this->name));
        }

        if ($this->isPrimaryOwner($to)) {
            return;
        }

        $previous = $this->user_id;

        $this->update(['user_id' => $to->getKey()]);
        $this->users()->updateExistingPivot($to->getKey(), ['is_owner' => false]);
        $this->users()->updateExistingPivot($previous, ['is_owner' => true]);
        $this->unsetRelation('owner');
    }

    /** Whether a member may hold more than one team role (config `teams.multiple_roles_per_member`). */
    public static function allowsMultipleRoles(): bool
    {
        return (bool) config('teams.multiple_roles_per_member', false);
    }

    /** The most teams one user may own, or null for unlimited (config `teams.max_teams_per_user`). */
    public static function ownedLimit(): ?int
    {
        $max = config('teams.max_teams_per_user');

        return $max === null || $max === '' ? null : max(0, (int) $max);
    }

    /** Whether the user already owns as many teams as they're allowed (see TeamPolicy::create). */
    public static function hasReachedOwnedLimit(User $user): bool
    {
        $limit = self::ownedLimit();

        return $limit !== null && $user->ownedTeams()->count() >= $limit;
    }

    /**
     * Whether the member holds the given team permission, resolved in this
     * team's scope. Ownership bypasses this at the policy level (TeamPolicy::before).
     */
    public function memberHasPermission(User $user, TeamPermission $permission): bool
    {
        if ($this->isSuspended($user)) {
            return false;
        }

        return app(TeamContext::class)->run($this, function () use ($user, $permission): bool {
            $user->unsetRelation('roles');

            return $user->checkPermissionTo($permission->value);
        });
    }

    /**
     * The member's role names within this team, resolved in the team's scope.
     *
     * @return SupportCollection<int, string>
     */
    public function rolesFor(User $user): SupportCollection
    {
        return app(TeamContext::class)->run($this, function () use ($user): SupportCollection {
            $user->unsetRelation('roles');

            return $user->getRoleNames()->values();
        });
    }

    /** The member's (first) role name within this team — the whole answer when roles are single per member. */
    public function roleFor(User $user): ?string
    {
        return $this->rolesFor($user)->first();
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
     * The role a new team's owner is given at setup (config
     * `teams.default_owner_role`), defaulting to the seeded "{Team} Admin" role
     * for the current labels. Ownership carries no permissions of its own, so
     * this role is where the owner's authority comes from. The name may not
     * resolve to an existing role (renamed/deleted) — callers assign it only
     * when it exists.
     */
    public static function defaultOwnerRole(): string
    {
        $configured = config('teams.default_owner_role');

        return is_string($configured) && $configured !== ''
            ? $configured
            : TeamLabels::singular().' Admin';
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
     * Each member's team roles in one query: user id => list of role names.
     * spatie resolves roles per team scope; this reads the pivot directly so an
     * admin screen can list a team's members without entering its scope (which
     * would also re-scope the *viewer's* own permission checks).
     *
     * @return SupportCollection<int, list<string>>
     */
    public function memberRoles(): SupportCollection
    {
        $roles = (new Role)->getTable();
        $pivot = config('permission.table_names.model_has_roles');
        $teamKey = config('permission.column_names.team_foreign_key', 'team_id');
        $morphKey = config('permission.column_names.model_morph_key', 'model_id');

        return Role::query()
            ->join($pivot, "{$pivot}.role_id", '=', "{$roles}.id")
            ->where("{$pivot}.{$teamKey}", $this->getKey())
            ->where("{$pivot}.model_type", (new User)->getMorphClass())
            ->orderBy("{$roles}.name")
            ->get(["{$roles}.name", "{$pivot}.{$morphKey} as member_id"])
            ->groupBy('member_id')
            ->map(fn (SupportCollection $rows): array => $rows->pluck('name')->values()->all());
    }

    /**
     * Set a member's team roles (replacing any held). Works for any member,
     * owners included: ownership is structural (a pivot flag), so an owner may
     * also hold a role — it shows alongside their owner badge and, under the
     * managed model, is where their authority comes from. Who is allowed to
     * change an owner's role is enforced at the UI/policy layer, not here.
     *
     * @param  list<string>  $roles
     *
     * @throws InvalidArgumentException for a non-team role, or several roles when the project allows one per member
     */
    public function syncMemberRoles(User $user, array $roles): void
    {
        $roles = array_values(array_unique(array_filter($roles)));

        if (count($roles) > 1 && ! static::allowsMultipleRoles()) {
            throw new InvalidArgumentException('A member may hold one team role (config teams.multiple_roles_per_member).');
        }

        foreach ($roles as $role) {
            if (! static::availableRoles()->where('name', $role)->exists()) {
                throw new InvalidArgumentException(sprintf("'%s' is not a team role.", $role));
            }
        }

        app(TeamContext::class)->run($this, fn () => $user->syncRoles($roles));
    }

    /** Set a member's single team role — see syncMemberRoles(). */
    public function changeMemberRole(User $user, string $role): void
    {
        $this->syncMemberRoles($user, [$role]);
    }

    /** Remove a member and their team roles. The primary owner is never removed from their own team. */
    public function removeMember(User $user): void
    {
        if ($this->isPrimaryOwner($user)) {
            return;
        }

        app(TeamContext::class)->run($this, fn () => $user->syncRoles([]));
        $this->users()->detach($user->getKey());
    }

    /**
     * Add a user to the team, optionally holding one or more of the shared team
     * roles. The single write path for membership (seeders, invitations,
     * tests): the pivot row and the spatie role assignment are two writes that
     * must agree.
     *
     * @param  string|list<string>|null  $roles
     *
     * @throws InvalidArgumentException for a non-team role, or several roles when the project allows one per member
     */
    public function addMember(User $user, string|array|null $roles = null): void
    {
        $roles = array_values(array_filter((array) $roles));

        $this->users()->syncWithoutDetaching([$user->getKey()]);

        if ($roles !== []) {
            $this->syncMemberRoles($user, $roles);
        }
    }
}
