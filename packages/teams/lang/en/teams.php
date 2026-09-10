<?php

/*
|--------------------------------------------------------------------------
| Teams tier strings
|--------------------------------------------------------------------------
|
| Short keys, read through team_trans('group.key') — which also supplies the
| project's word for a team from config('teams.labels'): `:team` / `:teams`
| are the lower-case singular / plural ("team", "salon"), and `:Team` /
| `:Teams` their sentence-case forms (Laravel capitalises a placeholder whose
| name is capitalised). A specific team's *name* is always `:name`, a person
| always `:member`. Publish to lang/vendor/teams to override.
|
*/

return [

    'inactive' => 'This :team is currently inactive.',

    'nav' => [
        'dashboard' => 'Dashboard',
        'overview' => 'Overview',
        'members' => 'Members',
        'invitations' => 'Invitations',
        'settings' => 'Settings',
    ],

    'onboarding' => [
        'title' => 'You’re not part of a :team yet',
        'can_create' => 'Create one to get started, or ask an admin to invite you.',
        'ask_admin' => 'Ask an administrator to add you to a :team.',
    ],

    'dashboard' => [
        'subtitle' => ':Team dashboard',
        'members' => 'Members',
        'placeholder' => 'This :team’s content will live here.',
    ],

    'members' => [
        'title' => 'Members',
        'description' => 'Manage who belongs to :name and their roles.',
        'search' => 'Search members…',
        'empty' => 'No members found',
        'role' => 'Role',
        'roles' => 'Roles',
        'no_role' => 'No role',
        'no_roles' => 'No roles',
        'owners' => 'Owners',
        'active' => 'Active',
        'suspended' => 'Suspended',
        'primary_owner' => 'Primary Owner',
        'owner' => 'Owner',
        'change_role' => 'Change role',
        'change_roles' => 'Change roles',
        'roles_updated' => 'Roles updated',
        'make_owner' => 'Make owner',
        'make_owner_confirm' => 'Make :member a co-owner of :name? Owners bypass every :team permission; only the primary owner can demote them.',
        'made_owner' => ':member is now an owner',
        'revoke_owner' => 'Remove as owner',
        'revoke_owner_confirm' => 'Remove :member as an owner of :name? They stay a member.',
        'revoked_owner' => ':member is no longer an owner',
        'transfer' => 'Transfer ownership',
        'transfer_confirm' => 'Make :member the primary owner of :name? The current primary owner stays on as a co-owner.',
        'transferred' => ':member is now the primary owner',
        'suspend' => 'Suspend',
        'suspend_confirm' => 'Suspend :member from :name? They keep their membership and role but can’t use the :team until reinstated.',
        'suspended_notice' => ':member suspended',
        'reinstate' => 'Reinstate',
        'reinstated' => ':member reinstated',
        'remove' => 'Remove',
        'remove_confirm' => 'Remove :member from :name?',
        'removed' => 'Member removed',
        'add' => 'Add member',
        'add_heading' => 'Add a member to :name',
        'added' => ':member added to :name',
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
        'already_member' => 'That person is already a member.',
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

    'admin' => [
        'description' => 'Every :team on the system — who owns it and who belongs to it.',
        'search' => 'Search :teams…',
        'empty' => 'No :teams found',
        'name' => 'Name',
        'owner' => 'Owner',
        'primary_owner' => 'Primary owner',
        'members' => 'Members',
        'owners' => 'Owners',
        'suspended' => 'Suspended',
        'pending_invitations' => 'Pending invitations',
        'created' => 'Created',
        'updated' => 'Last updated',
        'slug' => 'Slug',
        'slug_help' => 'The :team’s URL segment.',
        'active' => 'Active',
        'inactive' => 'Inactive',
        'active_help' => 'Inactive :teams keep their members but can’t be entered.',
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
        'deactivate_confirm' => 'Deactivate :name? Its members keep their membership but can’t use it until it’s reactivated.',
        'reactivate_confirm' => 'Reactivate :name? Its members regain access immediately.',
        'activated' => ':name activated',
        'deactivated' => ':name deactivated',
        'deactivate_first' => 'Deactivate the :team first.',
        'deactivate_before_delete' => 'Deactivate the :team before deleting it.',
        'delete' => 'Delete :Team',
        'delete_warning' => '{0} Delete :name? It can be restored from the trash.|{1} Delete :name? Its one member loses access until it is restored from the trash.|[2,*] Delete :name? Its :count members lose access until it is restored from the trash.',
        'force_delete_warning' => '{0} Permanently delete :name? This cannot be undone.|{1} Permanently delete :name? Its one member’s membership and role are erased with it. This cannot be undone.|[2,*] Permanently delete :name? Its :count members’ memberships and roles are erased with it. This cannot be undone.',
    ],

    'memberships' => [
        'title' => ':Team memberships',
        'none' => 'Not a member of any :team.',
    ],

    'roles' => [
        'scope_description' => 'Define what members of a :team can do. These roles are shared by every :team; the owner bypasses them.',
    ],

    'permissions' => [
        'manage_members' => 'Manage Members',
        'invite_members' => 'Invite Members',
        'update_team' => 'Update :Team Settings',
        'category_members' => 'Members',
        'category_settings' => ':Team Settings',
    ],

];
