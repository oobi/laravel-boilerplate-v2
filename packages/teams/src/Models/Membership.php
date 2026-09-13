<?php

declare(strict_types=1);

namespace Concise\Teams\Models;

use App\Models\Role;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * The `team_user` membership pivot. A member's team roles hang off it
 * (roles(), the `team_user_role` table) as foreign keys to spatie `roles` rows
 * in the `team` scope — never a `role` string column, which is what drifted in
 * the predecessor app. spatie owns the roles and their permissions; only the
 * per-team assignment is the app's, so spatie's teams feature stays off and
 * system roles resolve exactly as spatie documents. `is_owner` marks a
 * co-owner (the primary owner is `teams.user_id`; see Team::isOwnedBy()).
 * `suspended_at` withholds access without removing the member.
 *
 * @property int $id
 * @property int $team_id
 * @property int $user_id
 * @property bool $is_owner
 * @property Carbon|null $suspended_at
 */
class Membership extends Pivot
{
    protected $table = 'team_user';

    public $incrementing = true;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_owner' => 'boolean',
            'suspended_at' => 'datetime',
        ];
    }

    /** The member's roles in this team. */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'team_user_role', 'team_user_id', 'role_id')
            ->withTimestamps();
    }
}
