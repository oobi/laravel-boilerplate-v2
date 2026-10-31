<?php

declare(strict_types=1);

return [
    'failed' => 'These credentials do not match our records.',
    'password' => 'The provided password is incorrect.',
    'throttle' => 'Too many login attempts. Please try again in :seconds seconds.',
    'password_throttle' => 'Too many wrong passwords. Try again in :seconds seconds.',

    'inactive' => 'These credentials do not match our records.',
    'no_workspace' => 'Your account isn’t set up to access anything yet. Please contact an administrator.',
    'current_password_mismatch' => 'The provided password does not match your current password.',
    'reset_link_sent' => 'If an account exists for that email, a password reset link has been sent.',

    'two_factor_locked_out' => 'Your account is locked because two factor authentication was not set up in time. Please contact an administrator to restore access.',
    'two_factor_grace_warning' => '{1} Two factor authentication is required for your account. Set it up within :count day, or you will be locked out and need an administrator to restore access.|[2,*] Two factor authentication is required for your account. Set it up within :count days, or you will be locked out and need an administrator to restore access.',
    'two_factor_grace_ended' => 'Your two factor authentication grace period has ended. Set it up now, or you will not be able to log in again without an administrator.',
    'two_factor_required_super_admin' => 'Two factor authentication is required for this account. Please set it up to protect it.',
    'two_factor_setup_cta' => 'Set up two factor authentication',
    'password_min_hint' => 'At least :min characters.',
    'verify_wrong_address' => 'Wrong address?',
    'verify_change_address' => 'Change it on your profile.',
    'email_changed_subject' => 'Your email address was changed',
    'email_changed_line' => 'The email address on your :app account was changed to :email.',
    'email_changed_not_you' => 'If you made this change, there is nothing else to do. If you didn\'t, contact an administrator straight away.',

    'google_continue' => 'Continue with Google',
    'google_or' => 'or',
    'google_cancelled' => 'Google sign-in was cancelled.',
    'google_expired' => 'Your Google sign-in took too long. Please try again.',
    'google_failed' => 'Google sign-in isn’t working right now. Please try again, or log in with your password.',
    'google_unverified_email' => 'Google hasn’t verified the email address on that account, so it can’t be used to log in.',
    'google_domain_not_allowed' => 'Log in with your :domains Google account.',
    'google_no_account' => 'No account here uses that Google address. Log in with your password, or ask an administrator for an account.',
    'google_link_from_profile' => 'That Google account can’t be used to log in yet. Log in with your password, then link it on your profile.',
    'google_unverified_account' => 'Log in with your password and verify your email address first. You can then log in with Google.',
    'google_linked_elsewhere' => 'That Google account is already linked to another account.',
    'google_already_linked' => 'A Google account is already linked. Unlink it first.',
    'google_linked' => 'Google account linked. You can now log in with it.',
    'google_unlinked' => 'Google account unlinked.',
    'google_linked_subject' => 'A Google account was linked to your account',
    'google_linked_line' => 'The Google account :email can now log in to your :app account.',
    // Laravel's reset and verification emails, in the app's sentence case (App\Notifications\Auth).
    'reset_password_subject' => 'Reset password notification',
    'reset_password_action' => 'Reset password',
    'verify_email_subject' => 'Verify email address',
    'verify_email_action' => 'Verify email address',
    'google_linked_not_you' => 'If you did this, there is nothing else to do. If you didn\'t, change your password, unlink it on your profile and contact an administrator straight away.',
];
