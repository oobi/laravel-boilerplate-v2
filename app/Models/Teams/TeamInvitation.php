<?php

declare(strict_types=1);

namespace App\Models\Teams;

use Database\Factories\Teams\TeamInvitationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A pending invitation for an email address to join a team. `role` is the spatie
 * role name to grant, scoped to the team, when the invitation is accepted.
 *
 * @property int $id
 * @property int $team_id
 * @property string $email
 * @property string|null $role
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

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
