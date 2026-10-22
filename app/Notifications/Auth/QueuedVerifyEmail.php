<?php

declare(strict_types=1);

namespace App\Notifications\Auth;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Laravel's verification email, sent from the queue so a mail outage can't
 * turn sign-up or an email change into a 500 (GitHub #46). The link still
 * comes from AppServiceProvider's VerifyEmail::createUrlUsing() (APP_URL, #58).
 * Encrypted on the queue like the reset email, for consistency.
 */
class QueuedVerifyEmail extends VerifyEmail implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;
}
