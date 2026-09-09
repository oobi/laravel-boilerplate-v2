<?php

declare(strict_types=1);

namespace Concise\Teams\Support;

use Spatie\Permission\DefaultTeamResolver;

/**
 * Resolves the spatie/laravel-permission "current team" id, defaulting to the
 * reserved SYSTEM scope (0) rather than null when no team is set.
 *
 * Why: with spatie's teams feature on, `model_has_roles.team_id` is a NOT-NULL
 * composite-primary-key column, so a null context (system/global role
 * assignment) cannot be stored. Real teams auto-increment from 1, so 0 is a safe
 * sentinel for "no team / system scope". This lets a user hold a system role
 * (scope 0) and per-team roles (scope N) at the same time (R5), with each
 * resolving only in its own scope (R4) — without editing the core permission
 * migration or spatie's schema. See ~dev/TEAMS_TIER_SCOPE.md §5.
 *
 * Team routes set the concrete team id via middleware; everything else (admin,
 * CLI, queues, seeders, tests) falls back to the system scope.
 */
class TeamPermissionResolver extends DefaultTeamResolver
{
    /** The reserved team id for system/global (non-team) scope. */
    public const SYSTEM_SCOPE = 0;

    public function getPermissionsTeamId(): int|string
    {
        return $this->teamId ?? self::SYSTEM_SCOPE;
    }
}
