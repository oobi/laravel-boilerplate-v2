<?php

declare(strict_types=1);

use Laravel\Fortify\Features;

return [

    'guard' => 'web',

    'passwords' => 'users',

    'username' => 'email',

    'email' => 'email',

    'lowercase_usernames' => true,

    'home' => '/',

    /*
     * Where a user with neither a system role nor an add-on destination (e.g. a
     * team) lands after login — the one app-specific branch of the post-login
     * cascade (see App\Http\Responses\LoginResponse). 'home' sends them to the
     * public landing page; 'reject' logs them back out with a "contact an admin"
     * notice, for a login-only backoffice with no public page.
     */
    'login_fallback' => env('LOGIN_FALLBACK', 'home'),

    'prefix' => '',

    // teams:start — when the teams tier's custom-domain overlay is in host mode,
    // auth belongs to the control-plane host only (admin_host), like the rest of
    // the admin area. Null (path mode, or teams absent) leaves auth unconstrained.
    'domain' => (bool) env('TEAMS_DOMAINS_ENABLED', false) ? env('TEAMS_DOMAINS_ADMIN_HOST') : null,
    // teams:end

    'middleware' => ['web'],

    'auth_middleware' => 'auth',

    'limiters' => [
        'login' => 'login',
        'two-factor' => 'two-factor',
    ],

    'views' => true,

    'features' => [
        Features::registration(),
        Features::resetPasswords(),
        Features::emailVerification(),
        Features::updateProfileInformation(),
        Features::updatePasswords(),
        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]),
    ],

];
