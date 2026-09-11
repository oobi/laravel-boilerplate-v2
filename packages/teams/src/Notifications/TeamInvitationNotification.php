<?php

declare(strict_types=1);

namespace Concise\Teams\Notifications;

use Concise\Teams\Models\TeamInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

/**
 * The "you've been invited" email, sent on demand to the invited address (which
 * may not have an account yet). Queued; if the invitation is revoked before the
 * job runs, the job is dropped rather than failed.
 */
class TeamInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public TeamInvitation $invitation) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $team = $this->invitation->team;

        return (new MailMessage)
            ->subject(team_trans('invitations.mail.subject', ['name' => $team->name]))
            ->line(team_trans('invitations.mail.intro', ['name' => $team->name, 'app' => config('app.name')]))
            ->line(team_trans('invitations.mail.account', ['email' => $this->invitation->email]))
            ->action(team_trans('invitations.mail.action'), $this->invitation->acceptUrl())
            ->line(team_trans('invitations.mail.ignore'));
    }
}
