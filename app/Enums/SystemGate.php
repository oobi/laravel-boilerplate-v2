<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Bespoke Gate abilities defined with Gate::define() in AppServiceProvider.
 * These are deliberately NOT SystemPermission cases: they must never be
 * grantable through the admin-configurable Roles screen — a role granting
 * itself the right to edit role definitions is privilege escalation — so they
 * are hardcoded super-admin-only. Catalogued here so they are discoverable and
 * typo-proof; the value is the Gate::define name. See .ai/rules/policies.md.
 */
enum SystemGate: string
{
    case MANAGE_ROLES = 'manage roles';
}
