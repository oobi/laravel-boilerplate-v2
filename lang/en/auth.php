<?php

declare(strict_types=1);

return [
    'failed' => 'These credentials do not match our records.',
    'password' => 'The provided password is incorrect.',
    'throttle' => 'Too many login attempts. Please try again in :seconds seconds.',

    'inactive' => 'These credentials do not match our records.',
    'no_workspace' => 'Your account isn’t set up to access anything yet. Please contact an administrator.',
    'current_password_mismatch' => 'The provided password does not match your current password.',
    'reset_link_sent' => 'If an account exists for that email, a password reset link has been sent.',

    'two_factor_locked_out' => 'Your account is locked because two factor authentication was not set up in time. Please contact an administrator to restore access.',
    'two_factor_grace_warning' => '{1} Two factor authentication is required for your account. Set it up within :count day, or you will be locked out and need an administrator to restore access.|[2,*] Two factor authentication is required for your account. Set it up within :count days, or you will be locked out and need an administrator to restore access.',
    'two_factor_grace_ended' => 'Your two factor authentication grace period has ended. Set it up now, or you will not be able to log in again without an administrator.',
    'two_factor_required_super_admin' => 'Two factor authentication is required for this account. Please set it up to protect it.',
    'two_factor_setup_cta' => 'Set Up Two Factor Authentication',
];
