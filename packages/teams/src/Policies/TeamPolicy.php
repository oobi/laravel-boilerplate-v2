<?php

declare(strict_types=1);

namespace Concise\Teams\Policies;

use App\Models\User;
use Concise\Teams\Enums\TeamAbility;
use Concise\Teams\Enums\TeamCreationMode;
use Concise\Teams\Enums\TeamOwnership;
use Concise\Teams\Enums\TeamPermission;
use Concise\Teams\Models\Team;
use Concise\Teams\Support\DomainPolicy;

/**
 * Per-instance team abilities, mirroring UserPolicy's scheme: coarse checks go
 * through spatie permissions (TeamPermission, resolved in the team's scope),
 * never role names. Owners are structural (Team::isOwnedBy) and bypass every
 * ability within their own team via before() — the team-level parallel of the
 * global super-admin Gate::before (which still applies here too). A few
 * abilities are the PRIMARY owner's alone: co-owners share the bypass for
 * everything else, so day-to-day owner work never waits on one person.
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

        // Managed (silo) model: ownership is identity, not power. Owners are
        // authorised exactly like members — through their team role — so there is
        // no bypass at all; everyone (owner included) falls through to the
        // permission checks below. See TeamOwnership.
        if (! TeamOwnership::current()->ownersBypass()) {
            return null;
        }

        // Sovereign (entity) model from here down: the team is self-governing and
        // ownership carries authority directly, without needing a role.

        // The primary owner (teams.user_id) is sovereign over their team — every
        // ability, including the destructive/handover acts only they may perform.
        if ($team->isPrimaryOwner($user)) {
            return true;
        }

        // A co-owner shares day-to-day authority but NOT the primary-owner-only
        // acts (delete / transfer ownership / manage co-owners) — those fall
        // through to their policy methods (which deny), reserving them to the
        // primary owner. isCoOwner() is false for a suspended co-owner: the flag
        // is kept but the bypass is paused until they're reinstated.
        if ($team->isCoOwner($user) && ! in_array($ability, self::PRIMARY_OWNER_ONLY, true)) {
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

    public function view(User $user, Team $team): bool
    {
        return $team->isActiveMember($user);
    }

    /**
     * May the user see the member roster (read-only)? Owners always can (floor,
     * even when managed), plus the dedicated view permission and — since managing
     * implies viewing — anyone who can manage members. Acting on members
     * (role/suspend/remove) is gated separately by manageMembers.
     */
    public function viewMembers(User $user, Team $team): bool
    {
        return $team->isOwnedBy($user)
            || $team->memberHasPermission($user, TeamPermission::VIEW_MEMBERS)
            || $team->memberHasPermission($user, TeamPermission::MANAGE_MEMBERS);
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
     * May the user open the team-area Settings page at all? Owners (read-only
     * floor, even when managed), the dedicated view-settings permission (see the
     * page without editing), plus anyone who can edit one of its sections — team
     * details or, when the overlay's on, domains. Viewing and editing are
     * separate permissions: editing within is gated per-section by update /
     * manageDomains (VIEW_SETTINGS never grants a write).
     */
    public function viewSettings(User $user, Team $team): bool
    {
        return $team->isOwnedBy($user)
            || $team->memberHasPermission($user, TeamPermission::VIEW_SETTINGS)
            || $team->memberHasPermission($user, TeamPermission::UPDATE_TEAM)
            || (DomainPolicy::enabled() && $team->memberHasPermission($user, TeamPermission::MANAGE_DOMAINS));
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
