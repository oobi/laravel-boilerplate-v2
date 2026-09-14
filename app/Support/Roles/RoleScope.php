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
     * Which permissions carry which others with them. The Roles form displays
     * it (an implied option shows as "Included with …", is ticked along with
     * its implier and can't be unticked while the implier is on) AND stores it:
     * a saved role is closed over its implications, so what spatie stores is
     * the truth and a check asks for exactly one permission. Every other write
     * path in the scope must close the same way — the teams tier declares its
     * map once on TeamPermission::implies() and Team::createRole() applies it;
     * this method derives from that. Because the closure is stored — as
     * `role_has_permissions` rows, one per implied permission — a scope that
     * adds an entry to its map owes roles written earlier the rows they now
     * lack (`bp:roles:sync-implications` inserts them for every scope;
     * additive, idempotent). Keyed by permission name, each mapping to the
     * names it carries one hop away — consumers close the chain through
     * App\Support\Roles\Implications, so "delete users → view users → access
     * admin panel" is declared as two entries. Return [] for a scope with none.
     *
     * @return array<string, list<string>>
     */
    public function implications(): array;

    /**
     * Permissions in the vocabulary that don't apply in this environment —
     * typically a feature that is switched off — so the Roles form doesn't
     * offer them. They stay in the vocabulary: a role that already holds one
     * (granted while the feature was on) keeps it across a save rather than
     * being silently stripped. Return [] when everything applies.
     *
     * @return list<string>
     */
    public function unavailable(): array;

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
