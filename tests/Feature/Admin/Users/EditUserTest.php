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

    /**
     * Regression for issue #7: the edit form validated email format but not
     * uniqueness, so saving a taken address hit the DB unique index and raised
     * a QueryException instead of a field error.
     */
    public function test_updating_a_user_to_a_taken_email_fails_validation_regardless_of_casing(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $target = User::factory()->create();
        User::factory()->create(['email' => 'taken@example.com']);

        Livewire::actingAs($admin)
            ->test(EditUser::class, ['user' => $target])
            ->set('data.email', 'TAKEN@Example.com')
            ->call('save')
            ->assertHasFormErrors(['email' => 'unique']);

        $this->assertNotSame('taken@example.com', $target->fresh()->email);
    }

    /** A soft-deleted user keeps their row, so their address stays reserved. */
    public function test_updating_a_user_to_a_soft_deleted_users_email_fails_validation(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $target = User::factory()->create();
        User::factory()->create(['email' => 'gone@example.com'])->delete();

        Livewire::actingAs($admin)
            ->test(EditUser::class, ['user' => $target])
            ->set('data.email', 'gone@example.com')
            ->call('save')
            ->assertHasFormErrors(['email' => 'unique']);
    }

    public function test_a_user_keeping_their_own_email_is_not_reported_as_a_duplicate(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $target = User::factory()->create(['email' => 'keeper@example.com']);

        Livewire::actingAs($admin)
            ->test(EditUser::class, ['user' => $target])
            ->set('data.first_name', 'Still')
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('keeper@example.com', $target->fresh()->email);
    }

    /**
     * Regression for issue #8: a mixed-case email saved here used to be stored
     * verbatim, while Fortify lowercases the login identifier — locking the
     * account out on a case-sensitive connection.
     */
    public function test_a_mixed_case_email_is_stored_lowercased(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $target = User::factory()->create();

        Livewire::actingAs($admin)
            ->test(EditUser::class, ['user' => $target])
            ->set('data.email', 'Mixed.Case@Example.TEST')
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('mixed.case@example.test', $target->fresh()->email);
    }
}
