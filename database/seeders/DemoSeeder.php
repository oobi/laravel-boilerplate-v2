<?php

namespace Database\Seeders;

// teams:start
use Concise\Teams\Database\Seeders\Demo\TeamSeeder;
use Concise\Teams\Database\Seeders\TeamRolesSeeder;
// teams:end
use Database\Seeders\Demo\UserSeeder;
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    // Deliberately NOT WithoutModelEvents — see DatabaseSeeder.

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(PermissionSeeder::class);

        // teams:start — teams role seeder (guarded as in DatabaseSeeder; uninstaller strips it)
        if (class_exists(TeamRolesSeeder::class)) {
            $this->call(TeamRolesSeeder::class);
        }
        // teams:end

        // system users
        $this->call(UserSeeder::class);

        // teams:start — demo teams, owned by and populated with the users above
        if (class_exists(TeamSeeder::class)) {
            $this->call(TeamSeeder::class);
        }
        // teams:end
    }
}
