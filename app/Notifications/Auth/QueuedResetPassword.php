<?php

declare(strict_types=1);

namespace App\Notifications\Auth;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Laravel's password reset email, sent from the queue so a mail outage can't
 * break the forgot-password form or an admin's "send reset link" (GitHub #46).
 * The link still comes from ResetPassword::createUrlUsing() (APP_URL, #58).
 * Encrypted on the queue: the job carries the raw token, which the database
 * otherwise only stores hashed.
 */
class QueuedResetPassword extends ResetPassword implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;
}
