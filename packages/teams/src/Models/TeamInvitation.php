<?php

declare(strict_types=1);

namespace Concise\Teams\Models;

use App\Models\User;
use Concise\Teams\Database\Factories\TeamInvitationFactory;
use Concise\Teams\Notifications\TeamInvitationNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

/**
 * A pending invitation for an email address to join a team. `role` is the
 * shared team role to grant on acceptance (null = member with no role). The
 * invitation is accepted through a signed link (acceptUrl) by a signed-in
 * account whose email matches; revoking is simply deleting the row, which
 * also invalidates the link.
 *
 * @property int $id
 * @property int $team_id
 * @property string $email
 * @property string|null $role
 * @property Carbon|null $created_at
 * @property-read Team $team
 */
class TeamInvitation extends Model
{
    /** @use HasFactory<TeamInvitationFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'email',
        'role',
    ];

    protected static function newFactory(): TeamInvitationFactory
    {
        return TeamInvitationFactory::new();
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** The signed link the invitee follows to accept; deleting the invitation invalidates it. */
    public function acceptUrl(): string
    {
        return URL::signedRoute('team.invitations.accept', ['invitation' => $this]);
    }

    /** Whether this invitation was addressed to the given account's email. */
    public function isFor(User $user): bool
    {
        return strcasecmp($this->email, (string) $user->email) === 0;
    }

    /** Email the invitation (again) to its address. */
    public function send(): void
    {
        Notification::route('mail', $this->email)->notify(new TeamInvitationNotification($this));
    }
}
