<?php

declare(strict_types=1);

namespace App\Notifications\Auth;

use App\Support\Auth\WelcomeLink;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent when an admin creates a user and chooses to email them a link to set
 * their own password (Admin > Users > New user). Not the reset email: "reset
 * your password" for an account they never had reads as phishing. Queued and
 * encrypted like the other auth mails, and only once the new account is
 * committed. The link is built when the mail is rendered, so its lifetime runs
 * from sending, and it reflects the account then: if a password was set in
 * the meantime, the link it carries is already dead.
 */
class WelcomeNewUser extends Notification implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public function __construct()
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
        $app = config('app.name');

        return (new MailMessage)
            ->subject(__('auth.welcome_subject', ['app' => $app]))
            ->line(__('auth.welcome_line', ['app' => $app]))
            ->line(__('auth.welcome_choose_line', ['time' => WelcomeLink::lifetime()]))
            ->action(__('auth.welcome_action'), WelcomeLink::url($notifiable));
    }
}
