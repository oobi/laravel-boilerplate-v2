<?php

namespace Tests\Unit\Policies;

use App\Enums\SystemPermission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class UserPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_update_anyone_including_self(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $otherAdmin = User::factory()->superAdmin()->create();
        $regular = User::factory()->create();

        $this->assertTrue(Gate::forUser($admin)->allows('update', $otherAdmin));
        $this->assertTrue(Gate::forUser($admin)->allows('update', $regular));
        $this->assertTrue(Gate::forUser($admin)->allows('update', $admin));
    }

    public function test_actor_with_manage_users_permission_can_update_regular_users_but_not_super_admins(): void
    {
        $manager = User::factory()->withPermission(SystemPermission::MANAGE_USERS)->create();
        $regular = User::factory()->create();
        $admin = User::factory()->superAdmin()->create();

        $this->assertTrue(Gate::forUser($manager)->allows('update', $regular));
        $this->assertFalse(Gate::forUser($manager)->allows('update', $admin));
    }

    public function test_regular_users_without_permission_cannot_update_anyone(): void
    {
        $regular = User::factory()->create();
        $other = User::factory()->create();

        $this->assertFalse(Gate::forUser($regular)->allows('update', $other));
    }

    public function test_only_super_admins_can_assign_roles(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $manager = User::factory()->withPermission(SystemPermission::MANAGE_USERS)->create();
        $regular = User::factory()->create();
        $otherAdmin = User::factory()->superAdmin()->create();

        $this->assertTrue(Gate::forUser($admin)->allows('assignRole', $regular));
        $this->assertFalse(Gate::forUser($admin)->allows('assignRole', $otherAdmin));
        $this->assertFalse(Gate::forUser($manager)->allows('assignRole', $regular));
    }

    public function test_only_super_admins_can_grant_or_revoke_super_admin_and_never_for_themselves(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $manager = User::factory()->withPermission(SystemPermission::MANAGE_USERS)->create();
        $regular = User::factory()->create();

        $this->assertTrue(Gate::forUser($admin)->allows('grantSuperAdmin', $regular));
        $this->assertFalse(Gate::forUser($admin)->allows('grantSuperAdmin', $admin));
        $this->assertFalse(Gate::forUser($manager)->allows('grantSuperAdmin', $regular));
    }

    public function test_super_admin_can_toggle_active_for_anyone_except_self(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $otherAdmin = User::factory()->superAdmin()->create();
        $regular = User::factory()->create();

        $this->assertTrue(Gate::forUser($admin)->allows('toggleActive', $otherAdmin));
        $this->assertTrue(Gate::forUser($admin)->allows('toggleActive', $regular));
        $this->assertFalse(Gate::forUser($admin)->allows('toggleActive', $admin));
    }

    public function test_actor_with_suspend_users_permission_can_toggle_active_for_regular_users_but_not_super_admins_or_self(): void
    {
        $support = User::factory()->support()->create();
        $regular = User::factory()->create();
        $admin = User::factory()->superAdmin()->create();

        $this->assertTrue(Gate::forUser($support)->allows('toggleActive', $regular));
        $this->assertFalse(Gate::forUser($support)->allows('toggleActive', $admin));
        $this->assertFalse(Gate::forUser($support)->allows('toggleActive', $support));
    }

    public function test_super_admin_can_create_delete_restore_and_force_delete(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $target = User::factory()->create();

        $this->assertTrue(Gate::forUser($admin)->allows('create', User::class));
        $this->assertTrue(Gate::forUser($admin)->allows('delete', $target));
        $this->assertTrue(Gate::forUser($admin)->allows('restore', $target));
        $this->assertTrue(Gate::forUser($admin)->allows('forceDelete', $target));
    }

    public function test_super_admin_cannot_delete_themselves(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->assertFalse(Gate::forUser($admin)->allows('delete', $admin));
    }

    public function test_support_cannot_create_delete_restore_or_force_delete(): void
    {
        $support = User::factory()->support()->create();
        $target = User::factory()->create();

        $this->assertFalse(Gate::forUser($support)->allows('create', User::class));
        $this->assertFalse(Gate::forUser($support)->allows('delete', $target));
        $this->assertFalse(Gate::forUser($support)->allows('restore', $target));
        $this->assertFalse(Gate::forUser($support)->allows('forceDelete', $target));
    }

    /** Demonstrates the point of admin-configurable roles — a different, non-super-admin actor granted DELETE_USERS can delete (but never themselves). */
    public function test_an_actor_granted_delete_users_permission_can_delete_but_not_themselves(): void
    {
        $remover = User::factory()->withPermission(SystemPermission::DELETE_USERS)->create();
        $target = User::factory()->create();

        $this->assertTrue(Gate::forUser($remover)->allows('delete', $target));
        $this->assertFalse(Gate::forUser($remover)->allows('delete', $remover));
    }

    public function test_only_super_admins_can_directly_reset_passwords(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $support = User::factory()->support()->create();
        $target = User::factory()->create();

        $this->assertTrue(Gate::forUser($admin)->allows('updatePasswordDirectly', $target));
        $this->assertFalse(Gate::forUser($support)->allows('updatePasswordDirectly', $target));
    }

    public function test_actor_with_manage_users_permission_can_send_a_password_reset_link_but_not_to_a_super_admin(): void
    {
        $manager = User::factory()->withPermission(SystemPermission::MANAGE_USERS)->create();
        $regular = User::factory()->create();
        $admin = User::factory()->superAdmin()->create();

        $this->assertTrue(Gate::forUser($manager)->allows('sendPasswordResetLink', $regular));
        $this->assertFalse(Gate::forUser($manager)->allows('sendPasswordResetLink', $admin));
    }

    /**
     * canBeImpersonated() reads the real auth guard (Auth::check()/Auth::id())
     * when no actor is passed, but UserPolicy::impersonate() always passes
     * the explicit actor — these use actingAs() to exercise the real rules.
     */
    public function test_super_admin_cannot_impersonate_another_super_admin_or_themselves(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $otherAdmin = User::factory()->superAdmin()->create();

        $this->actingAs($admin);

        $this->assertFalse(Gate::allows('impersonate', $otherAdmin));
        $this->assertFalse(Gate::allows('impersonate', $admin));
    }

    public function test_super_admin_can_impersonate_a_regular_user(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $regular = User::factory()->create();

        $this->actingAs($admin);

        $this->assertTrue(Gate::allows('impersonate', $regular));
    }

    public function test_neither_super_admin_nor_support_can_impersonate_an_inactive_user(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $support = User::factory()->support()->create();
        $target = User::factory()->inactive()->create();

        $this->assertFalse(Gate::forUser($admin)->allows('impersonate', $target));
        $this->assertFalse(Gate::forUser($support)->allows('impersonate', $target));
    }
}
