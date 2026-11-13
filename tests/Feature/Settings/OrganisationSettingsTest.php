<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Settings\OrganisationSettings;
use App\Support\DisplayTimezone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\LaravelSettings\Migrations\SettingsMigration;
use Tests\TestCase;

/** The time zone setting's first value, and how the display zone is resolved. */
class OrganisationSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_first_time_zone_comes_from_app_display_timezone(): void
    {
        $this->forgetTimezone();
        config(['app.display_timezone' => 'Australia/Perth']);

        $this->migration()->up();

        $this->assertSame('Australia/Perth', app(OrganisationSettings::class)->timezone);
    }

    public function test_without_app_display_timezone_it_starts_as_the_apps_own_zone(): void
    {
        $this->forgetTimezone();
        config(['app.display_timezone' => null, 'app.timezone' => 'UTC']);

        $this->migration()->up();

        $this->assertSame('UTC', app(OrganisationSettings::class)->timezone);
    }

    public function test_a_time_zone_already_set_is_kept(): void
    {
        app(OrganisationSettings::class)->fill(['timezone' => 'Asia/Tokyo'])->save();
        config(['app.display_timezone' => 'Australia/Perth']);

        $this->migration()->up();

        $this->assertSame('Asia/Tokyo', app(OrganisationSettings::class)->refresh()->timezone);
    }

    public function test_without_a_resolver_the_display_zone_reads_the_config(): void
    {
        $resolver = DisplayTimezone::resolver();

        try {
            DisplayTimezone::resolveUsing(null);
            config(['app.display_timezone' => 'Asia/Dubai']);

            $this->assertSame('Asia/Dubai', DisplayTimezone::name());
        } finally {
            DisplayTimezone::resolveUsing($resolver);
        }
    }

    private function forgetTimezone(): void
    {
        DB::table('settings')->where('group', 'organisation')->where('name', 'timezone')->delete();
        app()->forgetScopedInstances();
    }

    private function migration(): SettingsMigration
    {
        return require database_path('settings/2026_11_13_000000_create_organisation_settings.php');
    }
}
