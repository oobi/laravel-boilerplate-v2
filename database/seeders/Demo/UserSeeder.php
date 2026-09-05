<?php

namespace Database\Seeders\Demo;

use App\Enums\SystemRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Seed super admin user
        User::factory()->create([
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'email' => 'admin@email.com',
            'password' => bcrypt('password'),
            'system_role' => SystemRole::SUPER_ADMIN->value,
        ]);

        // Seed support user
        User::factory()->create([
            'first_name' => 'Support',
            'last_name' => 'User',
            'email' => 'support@email.com',
            'password' => bcrypt('password'),
            'system_role' => SystemRole::SUPPORT->value,
        ]);

        // seed 100 additional demo users
        User::factory(100)->create();
    }
}
