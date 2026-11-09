<?php

declare(strict_types=1);

namespace App\Support\Audit;

/**
 * The categories core logs under, stored in `log_name`. The set is open: a
 * project may log under its own string, so don't assume every entry has a case.
 */
enum AuditCategory: string
{
    /** Privileged acts: roles, super admin, impersonation, an admin setting a password. */
    case ACCESS = 'access';

    /** Signing in and out. */
    case AUTH = 'auth';

    /** A person's own account security: password, two-factor, linked accounts. */
    case SECURITY = 'security';

    /** Records created, changed or deleted. */
    case DATA = 'data';

    /** Scheduled tasks and queued jobs. */
    case SYSTEM = 'system';
}
