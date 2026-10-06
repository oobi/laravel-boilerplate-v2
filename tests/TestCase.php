<?php

namespace Tests;

use App\Support\AccountMenu\AccountMenuRegistry;
use App\Support\Auth\LoginRedirectRegistry;
use App\Support\Breadcrumbs;
use App\Support\Navigation\Registry\NavRegistry;
use App\Support\Panels\Registry\PanelRegistry;
use App\Support\Roles\RoleScopeRegistry;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    /**
     * The registries are static, so they outlive the application each test
     * boots, and every boot re-runs the providers that fill them. Without a
     * flush the panels, nav items and login redirects pile up test after test:
     * by the end of a serial run the user page resolves thousands of duplicate
     * panels and a single render takes seconds. Flushing before the boot gives
     * every test the registrations a real request would see. Core registries
     * only: bp:remove-teams deletes any test file that names the teams tier, so
     * the tier's own TeamNavRegistry relies on NavGroup::add() replacing by name.
     */
    protected function setUp(): void
    {
        PanelRegistry::flush();
        NavRegistry::flush();
        AccountMenuRegistry::flush();
        RoleScopeRegistry::flush();
        LoginRedirectRegistry::flush();
        Breadcrumbs::flush();

        parent::setUp();
    }

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
