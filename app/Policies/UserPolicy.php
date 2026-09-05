<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\SystemPermission;
use App\Models\User;

/**
 * Per-instance authorization for the Users admin area. Global, non-instance
 * abilities (access admin panel, manage system settings, etc.) are checked
 * directly via `hasPermissionTo()`/spatie's own Gate::before — see
 * .ai/rules/providers.md. The super-admin bypass for both is a single
 * global `Gate::before()` in AppServiceProvider, not a method here.
 */
class UserPolicy
{
    /** Any admin-panel user may view any profile; gated globally at mount(), no extra restriction here. */
    public function view(User $actor, User $target): bool
    {
        return true;
    }

    /** Reached only for non-super-admins — creating/deleting the user roster stays super-admin only, unlike editing. */
    public function create(User $actor): bool
    {
        return false;
    }

    /** Ordinary profile fields only — never implies role assignment (see assignRole()). */
    public function update(User $actor, User $target): bool
    {
        return $actor->checkPermissionTo(SystemPermission::MANAGE_USERS->value) && ! $target->isSuperAdmin();
    }

    /**
     * Deliberately its own ability, not folded into update() — a support-style
     * "manage users" grant must never implicitly let its holder assign roles
     * (this is the fix for a real reviewed vulnerability: a generic `update`
     * check let a non-super-admin persist an arbitrary elevated role through
     * the edit form). Super-admin only, not permission-gated — assigning
     * roles is itself a privileged, meta-level action.
     */
    public function assignRole(User $actor, User $target): bool
    {
        return $actor->isSuperAdmin() && ! $target->isSuperAdmin();
    }

    /** Never permission-gated — granting/revoking the super-admin flag itself must stay outside the configurable-role system. */
    public function grantSuperAdmin(User $actor, User $target): bool
    {
        return $actor->isSuperAdmin() && $actor->id !== $target->id;
    }

    /** Only super admins may set a password directly; everyone else must send a reset link. */
    public function updatePasswordDirectly(User $actor, User $target): bool
    {
        return false;
    }

    public function sendPasswordResetLink(User $actor, User $target): bool
    {
        return $actor->checkPermissionTo(SystemPermission::MANAGE_USERS->value) && ! $target->isSuperAdmin();
    }

    /** Nobody may toggle their own active state, including a super admin. */
    public function toggleActive(User $actor, User $target): bool
    {
        if ($actor->id === $target->id) {
            return false;
        }

        return $actor->checkPermissionTo(SystemPermission::SUSPEND_USERS->value) && ! $target->isSuperAdmin();
    }

    public function resetTwoFactorAuthentication(User $actor, User $target): bool
    {
        return $actor->checkPermissionTo(SystemPermission::MANAGE_USERS->value) && ! $target->isSuperAdmin();
    }

    /** Nobody may delete their own account, including a super admin. */
    public function delete(User $actor, User $target): bool
    {
        if ($actor->id === $target->id) {
            return false;
        }

        return $actor->checkPermissionTo(SystemPermission::DELETE_USERS->value);
    }

    public function restore(User $actor, User $target): bool
    {
        return $actor->checkPermissionTo(SystemPermission::DELETE_USERS->value);
    }

    public function forceDelete(User $actor, User $target): bool
    {
        return $actor->checkPermissionTo(SystemPermission::DELETE_USERS->value);
    }

    /** Delegates to the lab404/laravel-impersonate contract methods — see .ai/rules/models.md. */
    public function impersonate(User $actor, User $target): bool
    {
        return $actor->canImpersonate() && $target->canBeImpersonated($actor);
    }
}
