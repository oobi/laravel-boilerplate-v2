<?php

namespace Database\Seeders;

use App\Models\User;
// teams:start
use Concise\Teams\Database\Seeders\TeamRolesSeeder;
// teams:end
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    // Deliberately NOT WithoutModelEvents: spatie flushes its permission cache
    // from model events, and Team backfills slugs / owner membership the same
    // way. Suppressing events here silently breaks both (see TeamRolesScopeTest).

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(PermissionSeeder::class);

        // teams:start — teams tier role seeder (packages/teams). The class_exists
        // guard keeps this safe if the fence is ever left in place after removal;
        // the uninstaller strips the whole block. See ~dev/TEAMS_TIER_SCOPE.md §11.5.
        if (class_exists(TeamRolesSeeder::class)) {
            $this->call(TeamRolesSeeder::class);
        }
        // teams:end

        // User::factory()->create([
        //     'first_name' => 'Test',
        //     'last_name' => 'User',
        //     'email' => 'test@example.com',
        // ]);
    }
}
