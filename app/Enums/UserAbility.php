<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The per-instance abilities on UserPolicy, catalogued so call sites reference
 * a case instead of typing the policy method name. Each value MUST equal the
 * UserPolicy method it maps to — Laravel resolves the ability string to that
 * method. Why an enum: a mistyped ability is a silent deny (and the super-admin
 * Gate::before answers true to any string, so only non-super-admins ever notice
 * the bug) — an enum turns that into a load-time error and makes this the one
 * place to see what exists. See .ai/rules/policies.md.
 */
enum UserAbility: string
{
    case VIEW = 'view';
    case CREATE = 'create';
    case UPDATE = 'update';
    case ASSIGN_ROLE = 'assignRole';
    case GRANT_SUPER_ADMIN = 'grantSuperAdmin';
    case UPDATE_PASSWORD_DIRECTLY = 'updatePasswordDirectly';
    case SEND_PASSWORD_RESET_LINK = 'sendPasswordResetLink';
    case TOGGLE_ACTIVE = 'toggleActive';
    case RESET_TWO_FACTOR_AUTHENTICATION = 'resetTwoFactorAuthentication';
    case DELETE = 'delete';
    case RESTORE = 'restore';
    case FORCE_DELETE = 'forceDelete';
    case IMPERSONATE = 'impersonate';
}
