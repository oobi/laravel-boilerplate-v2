<?php

namespace Tests\Unit\Support\Roles;

use App\Enums\SystemPermission;
use App\Models\User;
use App\Support\Roles\SystemPermissionRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use LogicException;
use Tests\Fixtures\ClashingPermission;
use Tests\Fixtures\ReportPermission;
use Tests\TestCase;

class SystemPermissionRegistryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Core only, whatever add-ons booted with the app; the next test's boot re-registers them.
        SystemPermissionRegistry::flush();
    }

    public function test_lists_core_permissions_first_then_registered_ones_in_registration_order(): void
    {
        SystemPermissionRegistry::register(ReportPermission::class);

        $this->assertSame(
            [...SystemPermission::cases(), ...ReportPermission::cases()],
            SystemPermissionRegistry::all()->all(),
        );
    }

    public function test_registering_an_enum_twice_lists_its_permissions_once(): void
    {
        SystemPermissionRegistry::register(ReportPermission::class);
        SystemPermissionRegistry::register(ReportPermission::class);

        $this->assertCount(count(SystemPermission::cases()) + count(ReportPermission::cases()), SystemPermissionRegistry::all());
    }

    public function test_refuses_an_enum_that_redefines_a_known_permission(): void
    {
        try {
            SystemPermissionRegistry::register(ClashingPermission::class);
            $this->fail('A clashing permission name was registered.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('manage users', $exception->getMessage());
        }

        $this->assertSame(SystemPermission::MANAGE_USERS, SystemPermissionRegistry::find('manage users'));
    }

    public function test_finds_a_registered_permission_by_name_and_returns_null_for_an_unknown_one(): void
    {
        SystemPermissionRegistry::register(ReportPermission::class);

        $this->assertSame(ReportPermission::EXPORT_REPORTS, SystemPermissionRegistry::find('export reports'));
        $this->assertNull(SystemPermissionRegistry::find('launch rockets'));
    }

    public function test_groups_registered_permissions_under_their_own_category_after_cores(): void
    {
        SystemPermissionRegistry::register(ReportPermission::class);

        $categories = SystemPermissionRegistry::byCategory();

        $this->assertSame('Reports', array_key_last($categories));
        $this->assertSame(ReportPermission::cases(), $categories['Reports']);
    }

    public function test_closes_a_grant_over_implications_that_cross_into_core(): void
    {
        SystemPermissionRegistry::register(ReportPermission::class);

        $this->assertSame(
            [ReportPermission::EXPORT_REPORTS, ReportPermission::VIEW_REPORTS, SystemPermission::ACCESS_ADMIN_PANEL],
            SystemPermissionRegistry::withImplied([ReportPermission::EXPORT_REPORTS]),
        );
    }

    public function test_refuses_to_close_a_grant_over_a_permission_whose_enum_is_not_registered(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('[export reports] is not registered');

        SystemPermissionRegistry::withImplied([ReportPermission::EXPORT_REPORTS]);
    }

    public function test_flush_keeps_core_permissions(): void
    {
        SystemPermissionRegistry::register(ReportPermission::class);
        SystemPermissionRegistry::flush();

        $this->assertSame(SystemPermission::cases(), SystemPermissionRegistry::all()->all());
    }

    public function test_a_registered_permission_is_granted_and_checked_like_a_core_one(): void
    {
        SystemPermissionRegistry::register(ReportPermission::class);
        $exporter = User::factory()->withPermission(ReportPermission::EXPORT_REPORTS)->create();
        $other = User::factory()->withPermission(SystemPermission::VIEW_USERS)->create();

        $this->assertTrue($exporter->hasSystemPermission(ReportPermission::VIEW_REPORTS));
        $this->assertTrue($exporter->canAccessAdmin());
        $this->assertTrue(Gate::forUser($exporter)->allows(ReportPermission::EXPORT_REPORTS));
        $this->assertFalse($other->hasSystemPermission(ReportPermission::VIEW_REPORTS));
    }
}
