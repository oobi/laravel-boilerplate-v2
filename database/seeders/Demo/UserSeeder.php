<?php
namespace Database\Seeders\Demo;

use App\Models\User;
use App\Enums\SystemRole;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder {

    public function run(): void
    {
        // Seed super admin user
        User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'admin@email.com',
            'password' => bcrypt('password'),
            'system_role' => SystemRole::SUPER_ADMIN->value,
        ]);

        // Seed support user
        User::factory()->create([
            'name' => 'Support User',
            'email' => 'support@email.com',
            'password' => bcrypt('password'),
            'system_role' => SystemRole::SUPPORT->value,
        ]);

        // seed 100 additional demo users
        User::factory(100)->create();
    }
}
