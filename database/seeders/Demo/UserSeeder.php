<?php

namespace Database\Seeders\Demo;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Super admin — the flag, the one exception. Every other admin capability
        // is a role (seeded by SystemRolesSeeder), demonstrated by the two below.
        User::factory()->create([
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'email' => 'admin@email.com',
            'password' => bcrypt('password'),
            'is_super_admin' => true,
        ]);

        // A full admin without the master key, on the seeded Administrator role.
        User::factory()
            ->create([
                'first_name' => 'Admin',
                'last_name' => 'User',
                'email' => 'administrator@email.com',
                'password' => bcrypt('password'),
            ])
            ->assignRole(Role::systemRoles()->where('name', 'Administrator')->firstOrFail());

        // A limited desk user, on the seeded Support role.
        User::factory()
            ->create([
                'first_name' => 'Support',
                'last_name' => 'User',
                'email' => 'support@email.com',
                'password' => bcrypt('password'),
            ])
            ->assignRole(Role::systemRoles()->where('name', 'Support')->firstOrFail());

        // seed 100 additional demo users
        User::factory(100)->create();
    }
}
