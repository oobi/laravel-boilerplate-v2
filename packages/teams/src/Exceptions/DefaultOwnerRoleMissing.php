<?php

declare(strict_types=1);

namespace Concise\Teams\Exceptions;

use RuntimeException;

/**
 * Thrown by the user-facing creation paths (CreateTeam, the admin "Add team"
 * action) when no team role matches Team::defaultOwnerRole(). Ownership grants
 * no permissions of its own, so a team created without that role would leave
 * its owner unable to do anything in it — creation refuses loudly instead (the
 * same fail-loud stance as TeamContextMissing). Team::created itself stays
 * lenient so factories, seeders and imports keep working.
 */
class DefaultOwnerRoleMissing extends RuntimeException
{
    public static function make(string $role): self
    {
        return new self(sprintf(
            "No team role named '%s' exists to give the new owner. Create it on the Roles screen, "
            .'or point teams.default_owner_role at an existing team role.',
            $role,
        ));
    }
}
