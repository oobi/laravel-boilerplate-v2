<?php

namespace Tests;

use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    /**
     * Every admin-area check now goes through spatie/laravel-permission, which
     * requires the Permission rows to exist in the database — seeded once per
     * RefreshDatabase migration (not a test-by-test $this->seed() call), so it
     * survives every test's transaction rollback like the schema itself does.
     */
    protected function afterRefreshingDatabase()
    {
        $this->seed(PermissionSeeder::class);
    }

    /**
     * Skip the current test when the given Fortify feature (e.g.
     * Features::registration()) is switched off in config/fortify.php, so a
     * project that disables it keeps a green suite.
     */
    protected function skipUnlessFortifyFeature(string $feature): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped("Fortify feature [{$feature}] is disabled in config/fortify.php.");
        }
    }
}
