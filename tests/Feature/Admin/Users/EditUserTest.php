<?php

namespace Tests\Feature\Admin\Users;

use App\Livewire\Admin\Users\EditUser;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class EditUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admins_are_forbidden(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($user)->get("/admin/users/{$other->id}/edit")->assertForbidden();
    }

    public function test_admins_can_update_a_user(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $target = User::factory()->create();

        Livewire::actingAs($admin)
            ->test(EditUser::class, ['user' => $target])
            ->set('data.first_name', 'Updated')
            ->set('data.last_name', 'Name')
            ->call('save')
            ->assertRedirect(route('users.show', $target));

        $target->refresh();
        $this->assertSame('Updated Name', $target->name);
    }

    public function test_super_admins_can_assign_roles(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $target = User::factory()->create();
        Role::findOrCreate('Editor');

        Livewire::actingAs($admin)
            ->test(EditUser::class, ['user' => $target])
            ->callAction('manageRoles', data: ['roles' => ['Editor']]);

        $this->assertTrue($target->fresh()->hasRole('Editor'));
    }

    public function test_super_admins_can_grant_super_admin_but_not_to_themselves(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $target = User::factory()->create();

        Livewire::actingAs($admin)
            ->test(EditUser::class, ['user' => $target])
            ->callAction('toggleSuperAdmin');

        $this->assertTrue($target->fresh()->is_super_admin);

        Livewire::actingAs($admin)
            ->test(EditUser::class, ['user' => $admin])
            ->assertActionHidden('toggleSuperAdmin');
    }

    /**
     * Regression for a reviewed vulnerability: the edit form's role selector
     * used to let a support-level actor persist an arbitrary elevated role
     * (including super admin) through the generic `update` ability. Role/
     * super-admin assignment are now their own abilities, re-checked at the
     * write boundary — hidden from the UI *and* rejected if called directly.
     */
    public function test_support_cannot_assign_roles_or_grant_super_admin_even_via_a_direct_action_call(): void
    {
        $support = User::factory()->support()->create();
        $target = User::factory()->create();
        Role::findOrCreate('Editor');

        Livewire::actingAs($support)
            ->test(EditUser::class, ['user' => $target])
            ->assertActionHidden('manageRoles')
            ->assertActionHidden('toggleSuperAdmin');

        $this->actingAs($support);

        $this->assertFalse(Gate::allows('assignRole', $target));
        $this->assertFalse(Gate::allows('grantSuperAdmin', $target));
        $this->assertFalse($target->fresh()->is_super_admin);
    }

    public function test_support_can_update_a_regular_user(): void
    {
        $support = User::factory()->support()->create();
        $target = User::factory()->create();

        Livewire::actingAs($support)
            ->test(EditUser::class, ['user' => $target])
            ->set('data.first_name', 'Updated')
            ->set('data.last_name', 'Name')
            ->call('save')
            ->assertRedirect(route('users.show', $target));

        $this->assertSame('Updated Name', $target->fresh()->name);
    }

    public function test_support_is_forbidden_from_editing_a_super_admin(): void
    {
        $support = User::factory()->support()->create();
        $target = User::factory()->superAdmin()->create();

        $this->actingAs($support)->get("/admin/users/{$target->id}/edit")->assertForbidden();
    }

    public function test_super_admins_can_directly_reset_a_users_password(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $target = User::factory()->create();

        Livewire::actingAs($admin)
            ->test(EditUser::class, ['user' => $target])
            ->callAction('resetPassword', data: [
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

        $this->assertTrue(Hash::check('new-password', $target->fresh()->password));
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $target->email]);
    }

    public function test_support_sends_a_password_reset_link_instead_of_setting_one_directly(): void
    {
        Notification::fake();

        $support = User::factory()->support()->create();
        $target = User::factory()->create();
        $originalPassword = $target->password;

        Livewire::actingAs($support)
            ->test(EditUser::class, ['user' => $target])
            ->callAction('resetPassword');

        $this->assertSame($originalPassword, $target->fresh()->password);
        Notification::assertSentTo($target, ResetPassword::class);
    }
}
