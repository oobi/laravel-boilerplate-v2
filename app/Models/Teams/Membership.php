<?php

declare(strict_types=1);

namespace App\Models\Teams;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * The `team_user` membership pivot. It carries no `role` column by design —
 * per-team roles live in spatie/laravel-permission scoped by team_id (see
 * ~dev/TEAMS_TIER_SCOPE.md §5). Kept as an explicit pivot model so future
 * membership attributes have an obvious home.
 */
class Membership extends Pivot
{
    protected $table = 'team_user';

    public $incrementing = true;
}
