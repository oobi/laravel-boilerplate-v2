<?php

declare(strict_types=1);

namespace App\Notifications\Auth;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the account's address whenever a Google account is linked to it, at
 * a first Google login or from the profile, so a new way in that the owner
 * didn't add gets noticed. The Google address is masked as in
 * EmailAddressChanged. Queued like the other auth mails, encrypted (it holds
 * an address), and only once the link is committed.
 */
class GoogleAccountLinked extends Notification implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public function __construct(public string $googleEmail)
    {
        $this->afterCommit();
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('auth.google_linked_subject'))
            ->line(__('auth.google_linked_line', ['app' => config('app.name'), 'email' => EmailAddressChanged::mask($this->googleEmail)]))
            ->line(__('auth.google_linked_not_you'));
    }
}
