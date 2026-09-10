<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Labels
    |--------------------------------------------------------------------------
    |
    | The word this project uses for a "team". Surfaces across the team UI and
    | lang lines so a project can call a team a "salon", "workspace", "clinic",
    | etc. without renaming any class or table. See ~dev/TEAMS_TIER_SCOPE.md §8.
    |
    */

    'labels' => [
        'singular' => 'Team',
        'plural' => 'Teams',
    ],

    /*
    |--------------------------------------------------------------------------
    | Route prefix
    |--------------------------------------------------------------------------
    |
    | The URL segment for default (non-custom-domain) team routes:
    | `/{route_prefix}/{team-slug}/…`. Relabel alongside the labels above
    | (e.g. 'salons').
    |
    */

    'route_prefix' => 'teams',

    /*
    |--------------------------------------------------------------------------
    | Creation model
    |--------------------------------------------------------------------------
    |
    | Who may create teams and how members join. One of:
    |  - 'self-service'      users create teams and invite others
    |  - 'admin-provisioned' only system admins create teams / assign members
    |  - 'invitation-only'   admins provision; owners/admins invite members
    |
    */

    'creation' => 'self-service',

    /*
    |--------------------------------------------------------------------------
    | Roles per member
    |--------------------------------------------------------------------------
    |
    | Whether a member may hold several team roles at once (like system users)
    | or exactly one (a "position", the usual SaaS shape — the default). The
    | data layer supports both; this only changes validation and the role
    | picker. Flip it per project rather than working around it.
    |
    */

    'multiple_roles_per_member' => false,

    /*
    |--------------------------------------------------------------------------
    | Personal teams
    |--------------------------------------------------------------------------
    |
    | When true, every user is given a personal team on registration. Off by
    | default: users may belong to zero teams and onboarding handles the empty
    | state (see ~dev/TEAMS_TIER_SCOPE.md OQ1).
    |
    */

    'personal_teams' => false,

    /*
    |--------------------------------------------------------------------------
    | Isolation
    |--------------------------------------------------------------------------
    |
    | Per-team filesystem and cache isolation (safety-critical — see
    | ~dev/TEAMS_TIER_SCOPE.md §5.5). Team files are confined to
    | `teams/{team_id}` on the base disk; cache keys are namespaced per team.
    | Both are fail-loud: a scoped call without a resolved team throws.
    |
    */

    'filesystem' => [
        'disk' => env('TEAMS_FILESYSTEM_DISK', 'local'),
        'prefix' => 'teams',
    ],

    'cache' => [
        'prefix' => 'teams',
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom domains
    |--------------------------------------------------------------------------
    |
    | The optional custom-domain overlay. Off by default — teams are reached via
    | the path prefix above. Only verified domains ever route (OQ3).
    |
    */

    'domains' => [
        'enabled' => false,
    ],

];
