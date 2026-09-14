<?php

declare(strict_types=1);

namespace Concise\Teams\Policies;

use App\Enums\SystemPermission;
use App\Models\User;
use Concise\Teams\Enums\TeamAbility;
use Concise\Teams\Enums\TeamCreationMode;
use Concise\Teams\Enums\TeamPermission;
use Concise\Teams\Models\Team;

/**
 * Per-instance team abilities, mirroring UserPolicy's scheme: coarse checks go
 * through spatie permissions (TeamPermission, resolved in the team's scope),
 * never role names. Ownership is structural (Team::isOwnedBy) and is a shield
 * plus a responsibility anchor, never a permission bypass: the PRIMARY owner
 * alone holds the three non-delegable acts (granted in before()) and a floor
 * into Settings where they live (viewSettings); everything else any owner
 * does comes from their team role, like any member. The global super-admin
 * Gate::before still applies on top.
 */
class TeamPolicy
{
    /** What `view teams` opens on any team: the pages of the admin area, read-only. */
    private const READ_ONLY = [
        TeamAbility::VIEW->value,
        TeamAbility::VIEW_MEMBERS->value,
        TeamAbility::VIEW_SETTINGS->value,
    ];

    /** Never granted to a co-owner or through a team role — only `teams.user_id`, or a system admin via before(). */
    private const PRIMARY_OWNER_ONLY = [
        TeamAbility::MANAGE_OWNERS->value,
        TeamAbility::TRANSFER_OWNERSHIP->value,
        TeamAbility::DELETE->value,
    ];

    public function before(User $user, string $ability, mixed $team = null): ?bool
    {
        if (! $team instanceof Team) {
            return null;
        }

        // System-admin authority over any team, member or not — expressed once
        // here so components check a single TeamAbility and never OR the system
        // permission themselves. `view teams` is the read floor: the admin area
        // opens read-only on any team. `manage teams` is day-to-day management.
        // The destructive and ownership acts each need their own finer
        // permission (mirroring the user side: view / manage / suspend / delete),
        // so a support tier can manage memberships without being able to delete
        // teams or hand ownership around, and a stats-only admin can't browse
        // teams at all. The super-admin bypass has already run before this.
        if (in_array($ability, self::READ_ONLY, true)
            && $user->hasSystemPermission(SystemPermission::VIEW_TEAMS)) {
            return true;
        }

        if ($user->hasSystemPermission(SystemPermission::MANAGE_TEAMS)
            && ! in_array($ability, self::PRIMARY_OWNER_ONLY, true)) {
            return true;
        }

        if ($ability === TeamAbility::DELETE->value
            && $user->hasSystemPermission(SystemPermission::DELETE_TEAMS)) {
            return true;
        }

        if (in_array($ability, [TeamAbility::MANAGE_OWNERS->value, TeamAbility::TRANSFER_OWNERSHIP->value], true)
            && $user->hasSystemPermission(SystemPermission::MANAGE_TEAM_OWNERSHIP)) {
            return true;
        }

        // Membership is the prerequisite for every other team ability: a stale
        // role row (any detach that isn't removeMember(), an import, a support
        // script) must never keep granting after the person has left, and a
        // suspended member has no authority until reinstated.
        if (! $team->isActiveMember($user)) {
            return false;
        }

        // Ownership is a shield + responsibility anchor, NOT a permission bypass.
        // The only thing it grants directly is the handful of non-delegable acts
        // (transfer / delete / manage co-owners), and only to the PRIMARY owner
        // (teams.user_id). Everything else — including a co-owner's day-to-day
        // authority — comes from the member's team role, checked in the methods
        // below, so an owner never sees or does more than their role allows.
        if ($team->isPrimaryOwner($user) && in_array($ability, self::PRIMARY_OWNER_ONLY, true)) {
            // Whether an owner may delete their own team is deferred to site policy
            // via a config switch — some businesses reserve deletion to admins —
            // independent of who may create teams. A system admin with `delete
            // teams` always can (granted above, not subject to this switch).
            if ($ability === TeamAbility::DELETE->value) {
                return (bool) config('teams.owner_can_delete', true);
            }

            return true;
        }

        return null;
    }

    /**
     * Self-service creation: the mode must allow it and the user must be under
     * the owned-teams limit (config `teams.max_teams_per_user`). Class-level,
     * so there's no team to bypass with — before() falls through. Admins
     * provisioning teams go through the `manage teams` system permission
     * instead and are never subject to the limit.
     */
    public function create(User $user): bool
    {
        return TeamCreationMode::current()->allowsSelfServiceCreation()
            && ! Team::hasReachedOwnedLimit($user);
    }

    /** Active membership — already required by before(); kept explicit as the ability's own answer. */
    public function view(User $user, Team $team): bool
    {
        return $team->isActiveMember($user);
    }

    /**
     * May the user see the member roster (read-only)? The view permission —
     * which a stored role holding manage also holds, since roles are written
     * closed over TeamPermission::implies(). Ownership grants no visibility of
     * its own: an owner sees the roster through their role, like any member.
     * Acting on members (role/suspend/remove) is gated separately by manageMembers.
     */
    public function viewMembers(User $user, Team $team): bool
    {
        return $team->memberHasPermission($user, TeamPermission::VIEW_MEMBERS);
    }

    public function manageMembers(User $user, Team $team): bool
    {
        return $team->memberHasPermission($user, TeamPermission::MANAGE_MEMBERS);
    }

    public function invite(User $user, Team $team): bool
    {
        return $team->memberHasPermission($user, TeamPermission::INVITE_MEMBERS);
    }

    public function update(User $user, Team $team): bool
    {
        return $team->memberHasPermission($user, TeamPermission::UPDATE_TEAM);
    }

    public function manageDomains(User $user, Team $team): bool
    {
        return $team->memberHasPermission($user, TeamPermission::MANAGE_DOMAINS);
    }

    /**
     * May the user open the team-area Settings page at all? The view-settings
     * permission — which a stored role holding update team or manage domains
     * also holds (written closed over implies()) — and always the primary owner: the acts only they may
     * perform (co-owners, transfer, delete) live on this page, so this is a
     * responsibility floor for one person, not a permission bypass — every
     * section still checks its own ability, and a role-less primary owner gets
     * a read-only details form. Editing within is gated per-section by update /
     * manageDomains (VIEW_SETTINGS never grants a write).
     */
    public function viewSettings(User $user, Team $team): bool
    {
        return $team->isPrimaryOwner($user)
            || $team->memberHasPermission($user, TeamPermission::VIEW_SETTINGS);
    }

    /** Promoting/demoting co-owners is the primary owner's alone — granted by before(), never by a role. */
    public function manageOwners(User $user, Team $team): bool
    {
        return false;
    }

    /** Handing over primary ownership is the primary owner's alone — granted by before(), never by a role. */
    public function transferOwnership(User $user, Team $team): bool
    {
        return false;
    }

    /** Deleting a team is the primary owner's alone — granted by before(), never by a role. */
    public function delete(User $user, Team $team): bool
    {
        return false;
    }
}
