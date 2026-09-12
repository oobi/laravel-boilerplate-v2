<?php

namespace Concise\Teams\Database\Factories;

use Concise\Teams\Models\Domain;
use Concise\Teams\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Domain>
 */
class DomainFactory extends Factory
{
    protected $model = Domain::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'domain' => fake()->unique()->domainName(),
            'is_primary' => false,
            'verified_at' => null,
        ];
    }

    public function verified(): static
    {
        return $this->state(fn (): array => ['verified_at' => now()]);
    }

    public function primary(): static
    {
        return $this->state(fn (): array => ['is_primary' => true]);
    }
}
