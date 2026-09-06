<?php

namespace Database\Seeders\Demo;

use App\Enums\SystemPermission;
use App\Models\Role;
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
            'is_super_admin' => true,
        ]);

        // Demo non-super-admin role, showing what an admin-configured role looks like
        $support = Role::findOrCreate('Support');
        $support->givePermissionTo([
            SystemPermission::ACCESS_ADMIN_PANEL->value,
            SystemPermission::MANAGE_USERS->value,
            SystemPermission::SUSPEND_USERS->value,
            SystemPermission::IMPERSONATE_USERS->value,
        ]);

        $supportUser = User::factory()->create([
            'first_name' => 'Support',
            'last_name' => 'User',
            'email' => 'support@email.com',
            'password' => bcrypt('password'),
        ]);
        $supportUser->assignRole($support);

        // seed 100 additional demo users
        User::factory(100)->create();
    }
}
