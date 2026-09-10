<?php

namespace Database\Seeders;

use Concise\Teams\Database\Seeders\Demo\TeamSeeder;
use Concise\Teams\Database\Seeders\TeamRolesSeeder;
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

        // Teams tier (packages/teams) — guarded as in DatabaseSeeder.
        if (class_exists(TeamRolesSeeder::class)) {
            $this->call(TeamRolesSeeder::class);
        }

        // system users
        $this->call(UserSeeder::class);

        // demo teams, owned by and populated with the users above
        if (class_exists(TeamSeeder::class)) {
            $this->call(TeamSeeder::class);
        }
    }
}
