<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Labels
    |--------------------------------------------------------------------------
    |
    | The words this project uses for the tier's three domain nouns — a "team",
    | someone who belongs to one, and someone who owns one. They surface across
    | the UI and lang lines (as :team / :member / :owner placeholders, plus their
    | plural and capitalised forms), so a project can call a team a "salon", its
    | members "stylists" and its owner a "manager" without renaming any class or
    | table. See ~dev/TEAMS_TIER_SCOPE.md §8.
    |
    */

    'labels' => [
        'team' => [
            'singular' => env('TEAMS_LABEL_SINGULAR', 'Team'),
            'plural' => env('TEAMS_LABEL_PLURAL', 'Teams'),
        ],
        'member' => [
            'singular' => env('TEAMS_MEMBER_LABEL_SINGULAR', 'Member'),
            'plural' => env('TEAMS_MEMBER_LABEL_PLURAL', 'Members'),
        ],
        'owner' => [
            'singular' => env('TEAMS_OWNER_LABEL_SINGULAR', 'Owner'),
            'plural' => env('TEAMS_OWNER_LABEL_PLURAL', 'Owners'),
        ],
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

    'route_prefix' => env('TEAMS_ROUTE_PREFIX', 'teams'),

    /*
    |--------------------------------------------------------------------------
    | Creation model
    |--------------------------------------------------------------------------
    |
    | Who may create a team. One of:
    |  - 'self-service' any verified user may create a team for themselves
    |  - 'admin-only'   only system admins create teams (Admin › Teams)
    |
    | How people *join* a team is a separate concern — see `invitations` below.
    |
    */

    'creation' => env('TEAMS_CREATION', 'self-service'),

    /*
    |--------------------------------------------------------------------------
    | Invitations
    |--------------------------------------------------------------------------
    |
    | Whether email invitations are available, and to whom — two independent
    | switches so creation and invitation policy never contradict each other:
    |  - 'members' a team's owner/admins may invite from the team area
    |  - 'admins'  a system admin may invite from Admin › Teams
    |
    | A backoffice turns both off (admins create accounts and add them to teams);
    | a typical SaaS leaves both on. Off = no invitation surface for that party.
    |
    */

    'invitations' => [
        'members' => (bool) env('TEAMS_MEMBER_INVITATIONS', true),
        'admins' => (bool) env('TEAMS_ADMIN_INVITATIONS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Teams per user
    |--------------------------------------------------------------------------
    |
    | How many teams one user may OWN under self-service creation — null (the
    | default) for unlimited. Counts teams they own, not teams they belong to:
    | being invited to a tenth team shouldn't stop you making your own. System
    | admins provisioning teams (Admin > Teams) are never subject to it.
    |
    */

    'max_teams_per_user' => env('TEAMS_MAX_PER_USER'),

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

    'multiple_roles_per_member' => (bool) env('TEAMS_MULTIPLE_ROLES', false),

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
