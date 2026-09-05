<?php

namespace Tests;

use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

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
}
