<?php

declare(strict_types=1);

namespace App\Notifications\Auth;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Sent to the PREVIOUS address whenever an account's email changes, by the
 * user or an admin, so a takeover through a stolen session gets noticed
 * (GitHub #49). Sent from UserObserver::updated(). The new address is masked
 * (first character and domain): enough for the owner to recognise it, without
 * handing it in full to whoever reads the old inbox after a legitimate change.
 * Queued like the other auth mails, encrypted (it holds both addresses), and
 * only once the change is committed.
 */
class EmailAddressChanged extends Notification implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public function __construct(public string $newEmail)
    {
        $this->afterCommit();
    }

    /** j•••@example.com */
    public static function mask(string $email): string
    {
        return Str::substr(Str::before($email, '@'), 0, 1).'•••@'.Str::after($email, '@');
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('auth.email_changed_subject'))
            ->line(__('auth.email_changed_line', ['app' => config('app.name'), 'email' => self::mask($this->newEmail)]))
            ->line(__('auth.email_changed_not_you'));
    }
}
