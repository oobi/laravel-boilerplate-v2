<?php

namespace Concise\Teams\Database\Factories;

use App\Models\User;
use Concise\Teams\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Team>
 */
class TeamFactory extends Factory
{
    protected $model = Team::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'user_id' => User::factory(),
            'personal_team' => false,
            'active' => true,
        ];
    }

    public function personal(): static
    {
        return $this->state(fn (array $attributes): array => [
            'personal_team' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'active' => false,
        ]);
    }

    /** Own the team with the given user (SetUpTeam adds them as a member + owner). */
    public function ownedBy(User $user): static
    {
        return $this->state(fn (array $attributes): array => [
            'user_id' => $user->getKey(),
        ]);
    }
}
