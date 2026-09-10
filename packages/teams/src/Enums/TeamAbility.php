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
    case VIEW = 'view';
    case MANAGE_MEMBERS = 'manageMembers';
    case INVITE = 'invite';
    case UPDATE = 'update';
    case DELETE = 'delete';
}
