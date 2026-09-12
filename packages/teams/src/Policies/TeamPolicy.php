<?php

declare(strict_types=1);

namespace Concise\Teams\Policies;

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
    /** Never granted to a co-owner or through a role — only `teams.user_id` (or a system admin's own gate). */
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

        // Membership is the prerequisite for every team ability: a stale role
        // row (any detach that isn't removeMember(), an import, a support
        // script) must never keep granting after the person has left, and a
        // suspended member has no authority until reinstated. System admins
        // don't come through here — their `manage teams` gate is checked first
        // at the call sites — and the global super-admin bypass already ran.
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
            // Who creates, deletes: under admin-only provisioning the team is the
            // platform's, and only a system admin may delete it.
            if ($ability === TeamAbility::DELETE->value) {
                return TeamCreationMode::current()->allowsSelfServiceCreation();
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
     * which managing carries with it (TeamPermission::implies, enforced in
     * memberHasPermission). Ownership grants no visibility of its own: an
     * owner sees the roster through their role, like any member. Acting on
     * members (role/suspend/remove) is gated separately by manageMembers.
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
     * permission — which editing a section (update team, manage domains)
     * carries with it — and always the primary owner: the acts only they may
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
