<?php

namespace Tests\Unit\Policies;

use App\Enums\SystemRole;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class UserPolicyTest extends TestCase
{
    private function makeUser(?SystemRole $role = null, int $id = 1): User
    {
        $user = new User(['system_role' => $role?->value]);
        $user->id = $id;

        return $user;
    }

    public function test_super_admin_can_update_anyone_including_self(): void
    {
        $admin = $this->makeUser(SystemRole::SUPER_ADMIN, 1);
        $otherAdmin = $this->makeUser(SystemRole::SUPER_ADMIN, 2);
        $regular = $this->makeUser(null, 3);

        $this->assertTrue(Gate::forUser($admin)->allows('update', $otherAdmin));
        $this->assertTrue(Gate::forUser($admin)->allows('update', $regular));
        $this->assertTrue(Gate::forUser($admin)->allows('update', $admin));
    }

    public function test_support_can_update_regular_users_but_not_super_admins(): void
    {
        $support = $this->makeUser(SystemRole::SUPPORT, 1);
        $regular = $this->makeUser(null, 2);
        $admin = $this->makeUser(SystemRole::SUPER_ADMIN, 3);

        $this->assertTrue(Gate::forUser($support)->allows('update', $regular));
        $this->assertFalse(Gate::forUser($support)->allows('update', $admin));
    }

    public function test_regular_users_cannot_update_anyone(): void
    {
        $regular = $this->makeUser(null, 1);
        $other = $this->makeUser(null, 2);

        $this->assertFalse(Gate::forUser($regular)->allows('update', $other));
    }

    public function test_super_admin_can_toggle_active_for_anyone_except_self(): void
    {
        $admin = $this->makeUser(SystemRole::SUPER_ADMIN, 1);
        $otherAdmin = $this->makeUser(SystemRole::SUPER_ADMIN, 2);
        $regular = $this->makeUser(null, 3);

        $this->assertTrue(Gate::forUser($admin)->allows('toggleActive', $otherAdmin));
        $this->assertTrue(Gate::forUser($admin)->allows('toggleActive', $regular));
        $this->assertFalse(Gate::forUser($admin)->allows('toggleActive', $admin));
    }

    public function test_support_can_toggle_active_for_regular_users_but_not_super_admins_or_self(): void
    {
        $support = $this->makeUser(SystemRole::SUPPORT, 1);
        $regular = $this->makeUser(null, 2);
        $admin = $this->makeUser(SystemRole::SUPER_ADMIN, 3);

        $this->assertTrue(Gate::forUser($support)->allows('toggleActive', $regular));
        $this->assertFalse(Gate::forUser($support)->allows('toggleActive', $admin));
        $this->assertFalse(Gate::forUser($support)->allows('toggleActive', $support));
    }

    public function test_super_admin_can_create_delete_restore_and_force_delete(): void
    {
        $admin = $this->makeUser(SystemRole::SUPER_ADMIN, 1);
        $target = $this->makeUser(null, 2);

        $this->assertTrue(Gate::forUser($admin)->allows('create', User::class));
        $this->assertTrue(Gate::forUser($admin)->allows('delete', $target));
        $this->assertTrue(Gate::forUser($admin)->allows('restore', $target));
        $this->assertTrue(Gate::forUser($admin)->allows('forceDelete', $target));
    }

    public function test_super_admin_cannot_delete_themselves(): void
    {
        $admin = $this->makeUser(SystemRole::SUPER_ADMIN, 1);

        $this->assertFalse(Gate::forUser($admin)->allows('delete', $admin));
    }

    public function test_support_cannot_create_delete_restore_or_force_delete(): void
    {
        $support = $this->makeUser(SystemRole::SUPPORT, 1);
        $target = $this->makeUser(null, 2);

        $this->assertFalse(Gate::forUser($support)->allows('create', User::class));
        $this->assertFalse(Gate::forUser($support)->allows('delete', $target));
        $this->assertFalse(Gate::forUser($support)->allows('restore', $target));
        $this->assertFalse(Gate::forUser($support)->allows('forceDelete', $target));
    }

    public function test_only_super_admins_can_directly_reset_passwords(): void
    {
        $admin = $this->makeUser(SystemRole::SUPER_ADMIN, 1);
        $support = $this->makeUser(SystemRole::SUPPORT, 2);
        $target = $this->makeUser(null, 3);

        $this->assertTrue(Gate::forUser($admin)->allows('updatePasswordDirectly', $target));
        $this->assertFalse(Gate::forUser($support)->allows('updatePasswordDirectly', $target));
    }

    /**
     * canBeImpersonated() reads the real auth guard (Auth::check()/Auth::id()),
     * not the Gate "for user" override, so these two need actingAs() to
     * exercise the real self/super-admin-target rules rather than trivially
     * passing because no one is "logged in".
     */
    public function test_super_admin_cannot_impersonate_another_super_admin_or_themselves(): void
    {
        $admin = $this->makeUser(SystemRole::SUPER_ADMIN, 1);
        $otherAdmin = $this->makeUser(SystemRole::SUPER_ADMIN, 2);

        $this->actingAs($admin);

        $this->assertFalse(Gate::allows('impersonate', $otherAdmin));
        $this->assertFalse(Gate::allows('impersonate', $admin));
    }

    public function test_super_admin_can_impersonate_a_regular_user(): void
    {
        $admin = $this->makeUser(SystemRole::SUPER_ADMIN, 1);
        $regular = $this->makeUser(null, 2);

        $this->actingAs($admin);

        $this->assertTrue(Gate::allows('impersonate', $regular));
    }
}
