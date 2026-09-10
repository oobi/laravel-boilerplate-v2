<?php

declare(strict_types=1);

namespace Concise\Teams\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * The `team_user` membership pivot. It carries no `role` column by design —
 * per-team roles live in spatie/laravel-permission scoped by team_id (see
 * ~dev/TEAMS_TIER_SCOPE.md §5). `is_owner` marks a co-owner (the primary
 * owner is `teams.user_id`; see Team::isOwnedBy()). `suspended_at` withholds
 * access without removing the member (see Team::suspendMember()).
 *
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
}
