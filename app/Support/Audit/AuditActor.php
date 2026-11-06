<?php

declare(strict_types=1);

namespace App\Support\Audit;

/**
 * The actor types core assigns. The set is open: a project adds its own with
 * Audit::as(), so `actor_type` is a string and not a closed enum.
 */
final class AuditActor
{
    /** A signed-in person. */
    public const string USER = 'user';

    /** A web request with nobody signed in, such as a failed login. */
    public const string GUEST = 'guest';

    /** A queued job, with the person who dispatched it as causer. */
    public const string QUEUE = 'queue';

    /** The scheduler's own record of a task. */
    public const string SCHEDULER = 'scheduler';

    /** Anything else outside a web request: console commands, jobs nobody dispatched. */
    public const string SYSTEM = 'system';

    /** Hidden Context keys, so an actor set for a block also reaches jobs it dispatches. */
    public const string CONTEXT_TYPE = 'audit.actor_type';

    public const string CONTEXT_NAME = 'audit.actor_name';

    public const string CONTEXT_DISPATCHED_BY = 'audit.dispatched_by';

    public const string CONTEXT_IMPERSONATED_BY = 'audit.impersonated_by';
}
