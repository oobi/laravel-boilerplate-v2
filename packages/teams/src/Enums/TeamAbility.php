<?php

declare(strict_types=1);

namespace Concise\Teams\Enums;

/**
 * The per-instance abilities on TeamPolicy — the team counterpart of
 * App\Enums\UserAbility. Each value MUST equal the TeamPolicy method it maps
 * to. Reference these cases at call sites, never the literal method name: a
 * mistyped ability is a silent deny. See .ai/rules/policies.md.
 */
enum TeamAbility: string
{
    /** Class-level: check with `Gate::allows(TeamAbility::CREATE, Team::class)`. */
    case CREATE = 'create';

    case VIEW = 'view';
    case VIEW_SETTINGS = 'viewSettings';
    case VIEW_MEMBERS = 'viewMembers';
    case MANAGE_MEMBERS = 'manageMembers';
    case INVITE = 'invite';
    case UPDATE = 'update';
    case MANAGE_DOMAINS = 'manageDomains';

    /** Primary owner only (see TeamPolicy::PRIMARY_OWNER_ONLY). */
    case MANAGE_OWNERS = 'manageOwners';
    case TRANSFER_OWNERSHIP = 'transferOwnership';
    case DELETE = 'delete';
}
