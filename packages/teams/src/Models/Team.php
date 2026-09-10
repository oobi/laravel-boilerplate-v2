<?php

declare(strict_types=1);

namespace Concise\Teams\Models;

use App\Models\User;
use Concise\Teams\Database\Factories\TeamFactory;
use Concise\Teams\Support\TeamContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

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

    /**
     * The owner, or a member holding an admin-level role, may manage the team.
     *
     * REVIEW (flagged 2026-09-10): the `['owner', 'admin']` role-name check is
     * awkward once teams can define custom roles — it assumes those exact names
     * exist and are the admin-level ones. Per .ai/rules/policies.md, promote this
     * to a named team permission (e.g. `manage team members`) that the default
     * admin roles hold, and check the permission here instead of role names.
     */
    public function userIsAdmin(User $user): bool
    {
        if ($this->user_id === $user->getKey()) {
            return true;
        }

        return app(TeamContext::class)->run($this, function () use ($user): bool {
            $user->unsetRelation('roles');

            return $user->hasAnyRole(['owner', 'admin']);
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
}
