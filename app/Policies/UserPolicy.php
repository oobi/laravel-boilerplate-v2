<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\SystemPermission;
use App\Models\User;
use App\Support\Roles\Coverage;

/**
 * Per-instance authorization for the Users admin area. Global, non-instance
 * abilities (access admin panel, view users, etc.) are checked
 * directly via `hasPermissionTo()`/spatie's own Gate::before — see
 * .ai/rules/providers.md. The super-admin bypass for both is a single
 * global `Gate::before()` in AppServiceProvider, not a method here.
 *
 * Every act on another user needs its permission AND that the actor covers
 * the target (holds every permission they hold; App\Support\Roles\Coverage),
 * so nobody acts on someone with more access than themselves, and nobody but
 * a super admin acts on a super admin.
 */
class UserPolicy
{
    /** Anyone in the users area (`view users`, gated at mount()) may view any profile; no per-target restriction. */
    public function view(User $actor, User $target): bool
    {
        return true;
    }

    /** A new account starts with no roles, so creating needs only the permission. */
    public function create(User $actor): bool
    {
        return $actor->checkPermissionTo(SystemPermission::MANAGE_USERS->value);
    }

    /** Ordinary profile fields only — never implies role assignment (see assignRole()). */
    public function update(User $actor, User $target): bool
    {
        return $actor->checkPermissionTo(SystemPermission::MANAGE_USERS->value) && Coverage::coversUser($actor, $target);
    }

    /**
     * Deliberately its own ability, not folded into update(): a generic edit
     * must never carry role assignment (the fix for a reviewed vulnerability,
     * where the edit form persisted an elevated role). Held through "manage
     * users", never on yourself, and only on someone the actor covers; each
     * role added or removed must be covered too (Coverage::coversRole(), checked
     * where the roles are saved). Excluded from the super-admin bypass, so a
     * super admin answers here too: anyone but another super admin or themself.
     */
    public function assignRole(User $actor, User $target): bool
    {
        return $actor->id !== $target->id
            && ($actor->isSuperAdmin() || $actor->checkPermissionTo(SystemPermission::MANAGE_USERS->value))
            && Coverage::coversUser($actor, $target)
            && ! $target->isSuperAdmin();
    }

    /** Never permission-gated — granting/revoking the super-admin flag itself must stay outside the configurable-role system. */
    public function grantSuperAdmin(User $actor, User $target): bool
    {
        return $actor->isSuperAdmin() && $actor->id !== $target->id;
    }

    /**
     * Setting a password directly is its own permission; without it, a reset
     * link. Never your own, including a super admin: that goes through the
     * profile, which asks for the current password.
     */
    public function updatePasswordDirectly(User $actor, User $target): bool
    {
        return $actor->id !== $target->id
            && $actor->checkPermissionTo(SystemPermission::SET_USER_PASSWORDS->value) && Coverage::coversUser($actor, $target);
    }

    /** Not for yourself: your own password changes on your profile. */
    public function sendPasswordResetLink(User $actor, User $target): bool
    {
        return $actor->id !== $target->id
            && $actor->checkPermissionTo(SystemPermission::MANAGE_USERS->value) && Coverage::coversUser($actor, $target);
    }

    /** Nobody may toggle their own active state, including a super admin. */
    public function toggleActive(User $actor, User $target): bool
    {
        if ($actor->id === $target->id) {
            return false;
        }

        return $actor->checkPermissionTo(SystemPermission::SUSPEND_USERS->value) && Coverage::coversUser($actor, $target);
    }

    /** Never your own, including a super admin: the profile asks for the password. */
    public function resetTwoFactorAuthentication(User $actor, User $target): bool
    {
        return $actor->id !== $target->id
            && $actor->checkPermissionTo(SystemPermission::MANAGE_USERS->value) && Coverage::coversUser($actor, $target);
    }

    /**
     * Resetting a user's mandatory-2FA grace period, which also lifts a lockout
     * (see GracePeriod). Never your own, or mandatory 2FA could be put off forever.
     */
    public function manageTwoFactorGrace(User $actor, User $target): bool
    {
        return $actor->id !== $target->id
            && $actor->checkPermissionTo(SystemPermission::MANAGE_USERS->value) && Coverage::coversUser($actor, $target);
    }

    /** Nobody may delete their own account, including a super admin. */
    public function delete(User $actor, User $target): bool
    {
        if ($actor->id === $target->id) {
            return false;
        }

        return $actor->checkPermissionTo(SystemPermission::DELETE_USERS->value) && Coverage::coversUser($actor, $target);
    }

    public function restore(User $actor, User $target): bool
    {
        return $actor->checkPermissionTo(SystemPermission::DELETE_USERS->value) && Coverage::coversUser($actor, $target);
    }

    public function forceDelete(User $actor, User $target): bool
    {
        return $actor->checkPermissionTo(SystemPermission::DELETE_USERS->value) && Coverage::coversUser($actor, $target);
    }

    /** Delegates to the lab404/laravel-impersonate contract methods — see .ai/rules/models.md. */
    public function impersonate(User $actor, User $target): bool
    {
        return $actor->canImpersonate() && $target->canBeImpersonated($actor);
    }
}
