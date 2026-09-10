<?php

namespace Database\Seeders;

use App\Models\User;
use Concise\Teams\Database\Seeders\TeamRolesSeeder;
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

        // Teams tier (packages/teams). Guarded so this seeder still runs when
        // the package has been removed; the seeder itself is a no-op unless the
        // tier is active. See ~dev/TEAMS_TIER_SCOPE.md §11.5.
        if (class_exists(TeamRolesSeeder::class)) {
            $this->call(TeamRolesSeeder::class);
        }

        // User::factory()->create([
        //     'first_name' => 'Test',
        //     'last_name' => 'User',
        //     'email' => 'test@example.com',
        // ]);
    }
}
