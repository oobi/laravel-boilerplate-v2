<?php

declare(strict_types=1);

namespace App\Support\Roles;

/**
 * The application's role scopes — the tabs on the admin Roles screen. Edit
 * this file to add or reorder core scopes; add-ons register theirs with
 * RoleScopeRegistry::register() from their own service provider.
 */
class AdminRoleScopes
{
    public static function define(): void
    {
        RoleScopeRegistry::register(SystemRoleScope::class);
    }
}
