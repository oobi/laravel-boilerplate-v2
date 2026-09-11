<?php

declare(strict_types=1);

namespace App\Support\Roles;

/**
 * One family of roles managed on the admin Roles screen: the `roles.scope`
 * value it owns, the permission vocabulary its roles can hold, and the row
 * attributes a new role in it needs. Core registers the `system` scope
 * (App\Support\Roles\AdminRoleScopes); an add-on registers its own from its
 * service provider via RoleScopeRegistry::register() and gets a tab on the
 * screen — without touching the roles components or views.
 */
interface RoleScope
{
    /** The `roles.scope` column value this scope owns. */
    public function key(): string;

    /** Tab label. */
    public function label(): string;

    /** Page-header description while this scope's tab is open. */
    public function description(): string;

    /**
     * Assignable permissions, grouped for the form: category label => [permission name => label].
     *
     * @return array<string, array<string, string>>
     */
    public function permissions(): array;

    /**
     * Permission implications: selecting a permission also grants everything it
     * implies. Keyed by permission name, each mapping to the permissions it
     * pulls in (e.g. an "update" permission implies its "view" counterpart, so a
     * role that can edit can always read). Applied both in the Roles form (the
     * implied box auto-ticks) and on save. Return [] for a scope with none.
     *
     * @return array<string, list<string>>
     */
    public function implications(): array;

    /**
     * Extra attributes for a role created in this scope (its `scope` value and
     * anything role resolution needs), merged into Role::create().
     *
     * @return array<string, mixed>
     */
    public function attributes(): array;

    /** Tab order; lowest first, and the lowest is the scope the bare Roles route opens on. */
    public function order(): int;
}
