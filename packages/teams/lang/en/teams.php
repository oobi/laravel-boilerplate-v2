<?php

/*
|--------------------------------------------------------------------------
| Teams tier strings
|--------------------------------------------------------------------------
|
| Short keys, read through team_trans('group.key') — which also supplies the
| project's words from config('teams.labels'): `:team`/`:teams`,
| `:member`/`:members` and `:owner`/`:owners` are the lower-case singular /
| plural ("team"/"salon", "member"/"stylist", "owner"/"manager"), and their
| capitalised forms (`:Team`, `:Members`, `:Owner`, …) are sentence-cased by
| Laravel automatically. A specific team's *name* is always `:name`, a specific
| person `:person`. Publish to lang/vendor/teams to override.
|
*/

return [

    'inactive' => 'This :team is currently inactive.',

    'nav' => [
        'dashboard' => 'Dashboard',
        'overview' => 'Overview',
        'members' => ':Members',
        'invitations' => 'Invitations',
        'settings' => 'Settings',
        // Account-menu cross-area links. "My :Teams" distinguishes this (enter my workspace)
        // from the admin sidebar's ":Teams" (manage every team). "Admin" stays literal.
        'my_teams' => 'My :Teams',
        'admin_dashboard' => 'Admin dashboard',
    ],

    'onboarding' => [
        'title' => 'You’re not part of a :team yet',
        'can_create' => 'Create one to get started, or ask an admin to invite you.',
        'ask_admin' => 'Ask an administrator to add you to a :team.',
    ],

    'create' => [
        'action' => 'Create :Team',
        'heading' => 'Create a :team',
        'submit' => 'Create',
        'name' => 'Name',
        'name_help' => 'You can change this later.',
        'created' => ':name created',
        'not_allowed' => 'You can’t create a :team right now.',
        'throttled' => 'You’ve created several :teams just now — try again shortly.',
    ],

    'select' => [
        'title' => 'Your :teams',
        'description' => 'Choose which :team to open.',
        'all' => 'All :teams',
        // "Mine" here means owned, not merely joined — everything on the panel is already joined.
        'mine' => 'My :teams',
        'members' => '{0} No :members|{1} 1 :member|[2,*] :count :members',
    ],

    'dashboard' => [
        'subtitle' => ':Team dashboard',
        'members' => ':Members',
        'placeholder' => 'This :team’s content will live here.',
    ],

    'members' => [
        'title' => ':Members',
        'description' => 'Manage who belongs to :name and their roles.',
        'search' => 'Search :members…',
        'empty' => 'No :members found',
        'role' => 'Role',
        'roles' => 'Roles',
        'no_role' => 'No role',
        'no_roles' => 'No roles',
        'owners' => ':Owners',
        'active' => 'Active',
        'suspended' => 'Suspended',
        'primary_owner' => 'Primary :Owner',
        'owner' => ':Owner',
        'change_role' => 'Change role',
        'change_roles' => 'Change roles',
        'roles_updated' => 'Roles updated',
        'make_owner' => 'Make :owner',
        'make_owner_confirm' => 'Make :person a co-:owner of :name? :Owners bypass every :team permission; only the primary :owner can demote them.',
        'made_owner' => ':person is now an :owner',
        'revoke_owner' => 'Remove as :owner',
        'revoke_owner_confirm' => 'Remove :person as an :owner of :name? They stay a :member.',
        'revoked_owner' => ':person is no longer an :owner',
        'transfer' => 'Transfer ownership',
        'transfer_confirm' => 'Make :person the primary :owner of :name? The current primary :owner stays on as a co-:owner.',
        'transferred' => ':person is now the primary :owner',
        'suspend' => 'Suspend',
        'suspend_confirm' => 'Suspend :person from :name? They keep their membership and role but can’t use the :team until reinstated.',
        'suspended_notice' => ':person suspended',
        'reinstate' => 'Reinstate',
        'reinstated' => ':person reinstated',
        'remove' => 'Remove',
        'remove_confirm' => 'Remove :person from :name?',
        'removed' => ':Member removed',
        'add' => 'Add :member',
        'add_heading' => 'Add a :member to :name',
        'added' => ':person added to :name',
    ],

    'invitations' => [
        'title' => 'Invitations',
        'description' => 'Invite people to :name by email and manage invitations they haven’t accepted yet.',
        'search' => 'Search invitations…',
        'empty' => 'No pending invitations',
        'email' => 'Email',
        'role' => 'Role',
        'sent' => 'Sent',
        'invite' => 'Invite',
        'invite_heading' => 'Invite someone to :name',
        'already_member' => 'That person is already a :member.',
        'sent_to' => 'Invitation sent to :email',
        'resend' => 'Resend',
        'resent_to' => 'Invitation re-sent to :email',
        'revoke' => 'Revoke',
        'revoke_confirm' => 'Revoke the invitation for :email?',
        'revoked' => 'Invitation revoked',
        'wrong_account' => 'This invitation was sent to a different email address.',
        'joined' => 'You’ve joined :name.',
        'welcome' => 'Welcome — you’ve joined :name.',
        'sign_in_first' => 'Sign in as :email to accept your invitation to :name.',

        'mail' => [
            'subject' => 'You’ve been invited to join :name',
            'intro' => 'You have been invited to join the :name :team on :app.',
            'account' => 'If you already have an account for :email, you’ll be asked to sign in; if not, the link lets you create one.',
            'action' => 'Accept invitation',
            'ignore' => 'If you weren’t expecting this invitation, you can ignore this email.',
        ],

        'register' => [
            'title' => 'Join :name',
            'intro' => 'You’ve been invited to join :name on :app. Create your account to accept.',
            'email' => 'Email',
            'first_name' => 'First Name',
            'last_name' => 'Last Name',
            'password' => 'Password',
            'confirm_password' => 'Confirm Password',
            'already_registered' => 'Already registered?',
            'submit' => 'Create account and join',
        ],
    ],

    'domains' => [
        'title' => 'Domains',
        'description' => 'Custom domains that point to :name.',
        'search' => 'Search domains…',
        'empty' => 'No domains yet',
        'domain' => 'Domain',
        'status' => 'Status',
        'verified' => 'Verified',
        'pending' => 'Pending',
        'primary' => 'Primary',
        'add' => 'Add domain',
        'add_heading' => 'Add a domain to :name',
        'add_help' => 'The domain you’ll point at this :team, e.g. app.example.com.',
        'added' => ':domain added — add the DNS record and verify it to go live.',
        'verify' => 'Verify now',
        'verify_instructions' => 'Add this TXT record at your DNS provider, then verify:',
        'verified_notice' => ':domain verified',
        'verify_failed' => 'The TXT record wasn’t found yet — DNS can take a while to propagate. Try again shortly.',
        'make_primary' => 'Make primary',
        'made_primary' => ':domain is now the primary domain',
        'remove' => 'Remove',
        'remove_confirm' => 'Remove :domain from :name?',
        'removed' => 'Domain removed',
        'invalid' => 'Enter a valid domain, e.g. app.example.com.',
        'reserved' => 'That domain is reserved and can’t be used.',
        'taken' => 'That domain is already in use.',
    ],

    'admin' => [
        'description' => 'Every :team on the system — who owns it and who belongs to it.',
        'search' => 'Search :teams…',
        'empty' => 'No :teams found',
        'name' => 'Name',
        'owner' => ':Owner',
        'primary_owner' => 'Primary :owner',
        'members' => ':Members',
        'owners' => ':Owners',
        'suspended' => 'Suspended',
        'pending_invitations' => 'Pending invitations',
        'created' => 'Created',
        'updated' => 'Last updated',
        'slug' => 'Slug',
        'slug_help' => 'The :team’s URL segment.',
        'active' => 'Active',
        'inactive' => 'Inactive',
        'active_help' => 'Inactive :teams keep their :members but can’t be entered.',
        'add' => 'Add :Team',
        'created_notice' => ':Team created',
        'updated_notice' => ':Team updated',
        'deleted_notice' => ':Team deleted',
        'page_description' => 'View and manage :team information',
        'settings' => 'Settings',
        'back' => 'Back to :Teams',
        'information' => ':Team information',
        'details' => ':Team details',
        'danger_zone' => 'Danger zone',
        'danger_help' => 'Deactivate first, then delete. A deleted :team can be restored from the trash.',
        'deactivate_confirm' => 'Deactivate :name? Its :members keep their membership but can’t use it until it’s reactivated.',
        'reactivate_confirm' => 'Reactivate :name? Its :members regain access immediately.',
        'activated' => ':name activated',
        'deactivated' => ':name deactivated',
        'deactivate_first' => 'Deactivate the :team first.',
        'deactivate_before_delete' => 'Deactivate the :team before deleting it.',
        'delete' => 'Delete :Team',
        'delete_warning' => '{0} Delete :name? It can be restored from the trash.|{1} Delete :name? Its one :member loses access until it is restored from the trash.|[2,*] Delete :name? Its :count :members lose access until it is restored from the trash.',
        'force_delete_warning' => '{0} Permanently delete :name? This cannot be undone.|{1} Permanently delete :name? Its one :member’s membership and role are erased with it. This cannot be undone.|[2,*] Permanently delete :name? Its :count :members’ memberships and roles are erased with it. This cannot be undone.',
    ],

    'memberships' => [
        'title' => ':Team memberships',
        'none' => 'Not a :member of any :team.',
    ],

    'roles' => [
        'scope_description' => 'Define what :members of a :team can do. These roles are shared by every :team; the :owner bypasses them.',
    ],

    'permissions' => [
        'manage_members' => 'Manage :Members',
        'invite_members' => 'Invite :Members',
        'update_team' => 'Update :Team Settings',
        'category_members' => ':Members',
        'category_settings' => ':Team Settings',
    ],

];
