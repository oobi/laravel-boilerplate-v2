<?php

declare(strict_types=1);

namespace Concise\Teams\Notifications;

use Concise\Teams\Models\TeamInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

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
        $label = Str::lower(config('teams.labels.singular', 'Team'));

        return (new MailMessage)
            ->subject(__('You’ve been invited to join :team', ['team' => $team->name]))
            ->line(__('You have been invited to join the :team :label on :app.', [
                'team' => $team->name,
                'label' => $label,
                'app' => config('app.name'),
            ]))
            ->line(__('If you don’t have an account yet, register with this email address (:email) first, then open the link below.', [
                'email' => $this->invitation->email,
            ]))
            ->action(__('Accept invitation'), $this->invitation->acceptUrl())
            ->line(__('If you weren’t expecting this invitation, you can ignore this email.'));
    }
}
