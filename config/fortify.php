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

    'domain' => null,

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
