<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * Per-instance authorization for the Users admin area. Global, non-instance
 * abilities (access admin panel, manage system settings, etc.) stay as
 * SystemPermission Gates in AppServiceProvider — see .ai/rules/providers.md.
 */
class UserPolicy
{
    /**
     * Super admins bypass every ability below, except: deleting/deactivating
     * themselves (still denied), and `impersonate` (always deferred to its
     * own method — it has its own full rule set: self, nested impersonation,
     * and "super admins can't be impersonated" applies to *any* super admin
     * target, not just self, so a simple self-check here isn't enough).
     * $target arrives as the `User::class` string (not a model) for the
     * argument-less `create` ability, hence the `instanceof` guard below.
     */
    public function before(User $actor, string $ability, mixed $target = null): ?bool
    {
        if ($ability === 'impersonate') {
            return null;
        }

        if (! $actor->isSuperAdmin()) {
            return null;
        }

        $isSelf = $target instanceof User && $target->id === $actor->id;

        if ($isSelf && in_array($ability, ['delete', 'toggleActive'], true)) {
            return null;
        }

        return true;
    }

    /** Any admin-panel user may view any profile; gated globally at mount(), no extra restriction here. */
    public function view(User $actor, User $target): bool
    {
        return true;
    }

    /** Reached only for non-super-admins (before() handles super admins) — nobody else may create users. */
    public function create(User $actor): bool
    {
        return false;
    }

    /** Support may edit anyone except a super admin; super admins are handled by before(). */
    public function update(User $actor, User $target): bool
    {
        return $actor->isSupport() && ! $target->isSuperAdmin();
    }

    /** Reached only for non-super-admins — nobody else may set a password directly. */
    public function updatePasswordDirectly(User $actor, User $target): bool
    {
        return false;
    }

    /** Nobody may toggle their own active state, including a super admin (before() defers here for self). */
    public function toggleActive(User $actor, User $target): bool
    {
        if ($actor->id === $target->id) {
            return false;
        }

        return $actor->isSupport() && ! $target->isSuperAdmin();
    }

    /** A super admin may delete anyone except themselves (before() defers here for self). */
    public function delete(User $actor, User $target): bool
    {
        return $actor->isSuperAdmin() && $actor->id !== $target->id;
    }

    /** Reached only for non-super-admins — nobody else may restore a trashed user. */
    public function restore(User $actor, User $target): bool
    {
        return false;
    }

    /** Reached only for non-super-admins — nobody else may force-delete a user. */
    public function forceDelete(User $actor, User $target): bool
    {
        return false;
    }

    /** Delegates to the lab404/laravel-impersonate contract methods — see .ai/rules/models.md. */
    public function impersonate(User $actor, User $target): bool
    {
        return $actor->canImpersonate() && $target->canBeImpersonated();
    }
}
