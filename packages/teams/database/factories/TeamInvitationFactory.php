<?php

namespace Concise\Teams\Database\Factories;

use Concise\Teams\Models\Team;
use Concise\Teams\Models\TeamInvitation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeamInvitation>
 */
class TeamInvitationFactory extends Factory
{
    protected $model = TeamInvitation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'email' => fake()->unique()->safeEmail(),
            'role' => null,
            'expires_at' => now()->addDays(TeamInvitation::lifetimeInDays()),
        ];
    }

    /** Sent long enough ago that its link no longer works. */
    public function expired(): static
    {
        return $this->state(fn (): array => ['expires_at' => now()->subMinute()]);
    }
}
