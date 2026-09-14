<?php

namespace Database\Factories;

use App\Enums\SystemPermission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\SystemRolesSeeder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'active' => true,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'active' => false,
        ]);
    }

    public function superAdmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_super_admin' => true,
        ]);
    }

    /**
     * Grants the given SystemPermission(s) directly, creating the Permission
     * rows on demand and closing over their implications like every granting
     * path — `withPermission(MANAGE_TEAMS)` reaches the teams area because it
     * also holds `view teams` and `access admin panel`.
     */
    public function withPermission(SystemPermission ...$permissions): static
    {
        return $this->afterCreating(function (User $user) use ($permissions): void {
            $user->givePermissionTo(array_map(
                fn (SystemPermission $permission) => Permission::findOrCreate($permission->value),
                SystemPermission::withImplied($permissions),
            ));
        });
    }

    /**
     * A non-super-admin actor with a typical "manage users" role, for exercising
     * the coarse permission checks in tests. Built from SystemRolesSeeder's
     * definition (read, not run — tests don't seed) so the fixture can't drift
     * from what a fresh install actually ships.
     */
    public function support(): static
    {
        return $this->afterCreating(function (User $user): void {
            $role = Role::findOrCreate('Support');
            $role->givePermissionTo(collect(SystemPermission::withImplied(SystemRolesSeeder::defaults()['Support']['permissions']))
                ->map(fn (SystemPermission $permission): Permission => Permission::findOrCreate($permission->value))
                ->all());

            $user->assignRole($role);
        });
    }

    public function twoFactorEnabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => encrypt('test-secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['test-recovery-code'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }
}
