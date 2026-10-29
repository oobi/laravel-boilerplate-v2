<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * An outside identity provider a user can sign in with
 * (`social_accounts.provider`). Only Google today; see docs/boilerplate/authentication.md.
 */
enum SocialProvider: string
{
    case GOOGLE = 'google';
}
