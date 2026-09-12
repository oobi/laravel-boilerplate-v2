<?php

namespace Tests\Feature\Admin\Roles;

use App\Enums\SystemPermission;
use App\Models\Role;
use App\Support\Theme\DaisyColor;
use Database\Seeders\SystemRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * SystemRolesSeeder is the unit under test here, not fixture setup (tests.md).
 * Mirrors TeamRolesScopeTest's seeder cases for the app-wide role side.
 */
class SystemRolesSeederTest extends TestCase
{
    use RefreshDatabase;

    private function runSeeder(): void
    {
        (new SystemRolesSeeder)->run();
    }

    public function test_it_seeds_administrator_and_support_into_an_empty_set(): void
    {
        $this->runSeeder();

        $this->assertSame(2, Role::systemRoles()->count());

        $admin = Role::systemRoles()->where('name', 'Administrator')->firstOrFail();
        $this->assertSame(Role::SYSTEM_SCOPE, $admin->scope);
        $this->assertSame(DaisyColor::ERROR, $admin->color);

        // Administrator holds every system capability — the gap from super admin
        // is the acts that aren't permissions at all.
        foreach (SystemPermission::cases() as $permission) {
            $this->assertTrue($admin->hasPermissionTo($permission->value), $permission->value);
        }

        $support = Role::systemRoles()->where('name', 'Support')->firstOrFail();
        $this->assertSame(DaisyColor::WARNING, $support->color);
        $this->assertTrue($support->hasPermissionTo(SystemPermission::MANAGE_USERS->value));
        $this->assertFalse($support->hasPermissionTo(SystemPermission::DELETE_USERS->value), 'support is limited');
        $this->assertFalse($support->hasPermissionTo(SystemPermission::MANAGE_SYSTEM_SETTINGS->value));
    }

    public function test_it_seeds_only_into_an_empty_set(): void
    {
        Role::create(['name' => 'Custom Admin', 'guard_name' => 'web']); // an admin already owns the set

        $this->runSeeder();

        $this->assertFalse(Role::where('name', 'Administrator')->exists(), 'nothing is seeded once roles exist');
        $this->assertSame(1, Role::systemRoles()->count());
    }

    public function test_it_is_idempotent(): void
    {
        $this->runSeeder();
        $this->runSeeder();

        $this->assertSame(2, Role::systemRoles()->count());
    }

    public function test_it_fails_clearly_when_a_default_name_is_taken_in_another_scope(): void
    {
        // Role names are unique per guard across scopes; a clash is a real
        // conflict and gets a message, not a constraint error.
        Role::create(['name' => 'Administrator', 'guard_name' => 'web', 'scope' => 'team']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches("/scope 'team'/");

        $this->runSeeder();
    }

    public function test_a_deleted_default_stays_deleted(): void
    {
        $this->runSeeder();
        Role::systemRoles()->where('name', 'Support')->firstOrFail()->delete();

        $this->runSeeder(); // the set is non-empty now — nothing resurrects

        $this->assertFalse(Role::where('name', 'Support')->exists());
        $this->assertSame(1, Role::systemRoles()->count());
    }
}
