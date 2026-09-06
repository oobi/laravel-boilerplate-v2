<?php

namespace Database\Factories;

use App\Enums\SystemPermission;
use App\Models\Role;
use App\Models\User;
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

    /** Grants the given SystemPermission(s) directly, creating the Permission rows on demand. */
    public function withPermission(SystemPermission ...$permissions): static
    {
        return $this->afterCreating(function (User $user) use ($permissions): void {
            $user->givePermissionTo(array_map(
                fn (SystemPermission $permission) => Permission::findOrCreate($permission->value),
                $permissions,
            ));
        });
    }

    /** A non-super-admin actor with a typical "manage users" role, for exercising the coarse permission checks in tests. */
    public function support(): static
    {
        return $this->afterCreating(function (User $user): void {
            $role = Role::findOrCreate('Support');
            $role->givePermissionTo([
                Permission::findOrCreate(SystemPermission::ACCESS_ADMIN_PANEL->value),
                Permission::findOrCreate(SystemPermission::MANAGE_USERS->value),
                Permission::findOrCreate(SystemPermission::SUSPEND_USERS->value),
                Permission::findOrCreate(SystemPermission::IMPERSONATE_USERS->value),
            ]);

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
