<?php

namespace Tests\Unit\Support\Roles;

use App\Enums\SystemPermission;
use App\Models\User;
use App\Support\Roles\SystemPermissionRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use LogicException;
use Tests\Fixtures\AuditPermission;
use Tests\Fixtures\ClashingPermission;
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
        SystemPermissionRegistry::register(AuditPermission::class);

        $this->assertSame(
            [...SystemPermission::cases(), ...AuditPermission::cases()],
            SystemPermissionRegistry::all()->all(),
        );
    }

    public function test_registering_an_enum_twice_lists_its_permissions_once(): void
    {
        SystemPermissionRegistry::register(AuditPermission::class);
        SystemPermissionRegistry::register(AuditPermission::class);

        $this->assertCount(count(SystemPermission::cases()) + count(AuditPermission::cases()), SystemPermissionRegistry::all());
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
        SystemPermissionRegistry::register(AuditPermission::class);

        $this->assertSame(AuditPermission::EXPORT_AUDIT_LOG, SystemPermissionRegistry::find('export audit log'));
        $this->assertNull(SystemPermissionRegistry::find('launch rockets'));
    }

    public function test_groups_registered_permissions_under_their_own_category_after_cores(): void
    {
        SystemPermissionRegistry::register(AuditPermission::class);

        $categories = SystemPermissionRegistry::byCategory();

        $this->assertSame('Audit log', array_key_last($categories));
        $this->assertSame(AuditPermission::cases(), $categories['Audit log']);
    }

    public function test_closes_a_grant_over_implications_that_cross_into_core(): void
    {
        SystemPermissionRegistry::register(AuditPermission::class);

        $this->assertSame(
            [AuditPermission::EXPORT_AUDIT_LOG, AuditPermission::VIEW_AUDIT_LOG, SystemPermission::ACCESS_ADMIN_PANEL],
            SystemPermissionRegistry::withImplied([AuditPermission::EXPORT_AUDIT_LOG]),
        );
    }

    public function test_refuses_to_close_a_grant_over_a_permission_whose_enum_is_not_registered(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('[export audit log] is not registered');

        SystemPermissionRegistry::withImplied([AuditPermission::EXPORT_AUDIT_LOG]);
    }

    public function test_flush_keeps_core_permissions(): void
    {
        SystemPermissionRegistry::register(AuditPermission::class);
        SystemPermissionRegistry::flush();

        $this->assertSame(SystemPermission::cases(), SystemPermissionRegistry::all()->all());
    }

    public function test_a_registered_permission_is_granted_and_checked_like_a_core_one(): void
    {
        SystemPermissionRegistry::register(AuditPermission::class);
        $exporter = User::factory()->withPermission(AuditPermission::EXPORT_AUDIT_LOG)->create();
        $other = User::factory()->withPermission(SystemPermission::VIEW_USERS)->create();

        $this->assertTrue($exporter->hasSystemPermission(AuditPermission::VIEW_AUDIT_LOG));
        $this->assertTrue($exporter->canAccessAdmin());
        $this->assertTrue(Gate::forUser($exporter)->allows(AuditPermission::EXPORT_AUDIT_LOG));
        $this->assertFalse($other->hasSystemPermission(AuditPermission::VIEW_AUDIT_LOG));
    }
}
