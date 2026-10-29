<?php

declare(strict_types=1);

namespace Concise\Teams\Models;

use App\Models\User;
use App\Support\Auth\MailedLinks;
use Concise\Teams\Database\Factories\TeamInvitationFactory;
use Concise\Teams\Notifications\TeamInvitationNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

/**
 * A pending invitation for an email address to join a team. `role` is the
 * shared team role to grant on acceptance (null = member with no role). The
 * invitation is accepted through a signed link (acceptUrl) by a signed-in
 * account whose email matches; revoking is simply deleting the row, which
 * also invalidates the link. The link expires with the invitation
 * (`expires_at`, GitHub oobi#27), because it also lets its holder sign up
 * while public registration is closed.
 *
 * @property int $id
 * @property int $team_id
 * @property string $email
 * @property string|null $role
 * @property Carbon $expires_at
 * @property Carbon|null $created_at
 * @property-read Team $team
 */
class TeamInvitation extends Model
{
    /** @use HasFactory<TeamInvitationFactory> */
    use HasFactory;

    use Prunable;

    /** Days an expired invitation stays listed (marked expired) before it is pruned. */
    public const PRUNE_AFTER_DAYS = 30;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'email',
        'role',
        'expires_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    protected static function newFactory(): TeamInvitationFactory
    {
        return TeamInvitationFactory::new();
    }

    /** Days a newly sent invitation stays valid (config teams.invitations.expires_after_days). */
    public static function lifetimeInDays(): int
    {
        return max(1, (int) config('teams.invitations.expires_after_days', 7));
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * The signed link the invitee follows to accept. It expires with the
     * invitation, and deleting the invitation invalidates it. Built from
     * APP_URL, not the inviter's request (GitHub #58).
     */
    public function acceptUrl(): string
    {
        return MailedLinks::generator()->temporarySignedRoute('team.invitations.accept', $this->expires_at, ['invitation' => $this]);
    }

    /** The signed registration page (GET) or form action (POST) for an invitee without an account. */
    public function registerUrl(bool $store = false): string
    {
        $route = $store ? 'team.invitations.register.store' : 'team.invitations.register';

        return URL::temporarySignedRoute($route, $this->expires_at, ['invitation' => $this]);
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

    /**
     * Expired invitations, once they've been listed as expired for a while
     * (`model:prune`, scheduled by TeamsServiceProvider).
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->where('expires_at', '<', now()->subDays(self::PRUNE_AFTER_DAYS));
    }
}
