<?php

namespace Tests\Feature\Admin\Users;

use App\Livewire\Admin\Users\ShowUser;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class ShowUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admins_are_forbidden(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($user)->get("/admin/users/{$other->id}")->assertForbidden();
    }

    public function test_admins_can_view_a_user(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $target = User::factory()->create(['first_name' => 'Jane', 'last_name' => 'Doe']);

        $response = $this->actingAs($admin)->get("/admin/users/{$target->id}");

        $response->assertStatus(200);
        $response->assertSee('Jane Doe');
    }

    public function test_support_sees_edit_button_for_a_regular_user_but_not_a_super_admin(): void
    {
        $support = User::factory()->support()->create();
        $regular = User::factory()->create();
        $admin = User::factory()->superAdmin()->create();

        Livewire::actingAs($support)
            ->test(ShowUser::class, ['user' => $regular])
            ->assertSee(__('admin.edit_user'));

        Livewire::actingAs($support)
            ->test(ShowUser::class, ['user' => $admin])
            ->assertDontSee(__('admin.edit_user'));
    }

    public function test_a_soft_deleted_user_can_still_be_viewed(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $target = User::factory()->create();
        $target->delete();

        Livewire::actingAs($admin)
            ->test(ShowUser::class, ['user' => $target])
            ->assertOk();
    }

    public function test_it_shows_the_statistics_panel_with_account_age_and_last_login(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $target = User::factory()->create(['last_login_at' => now()->subDay()]);

        Livewire::actingAs($admin)
            ->test(ShowUser::class, ['user' => $target])
            ->assertSee(__('admin.statistics'))
            ->assertSee(__('admin.account_age'))
            ->assertSee(__('admin.last_login'))
            ->assertSee('1 day ago');
    }

    public function test_the_statistics_panel_shows_never_when_the_user_has_not_logged_in(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $target = User::factory()->create(['last_login_at' => null]);

        Livewire::actingAs($admin)
            ->test(ShowUser::class, ['user' => $target])
            ->assertSee(__('admin.never'));
    }

    public function test_it_shows_two_factor_disabled_by_default(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $target = User::factory()->create();

        Livewire::actingAs($admin)
            ->test(ShowUser::class, ['user' => $target])
            ->assertSee(__('admin.disabled'));
    }

    public function test_it_shows_two_factor_enabled_when_confirmed(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $target = User::factory()->twoFactorEnabled()->create();

        Livewire::actingAs($admin)
            ->test(ShowUser::class, ['user' => $target])
            ->assertSee(__('admin.enabled'));
    }

    public function test_admins_can_force_disable_a_users_two_factor_authentication_after_confirming_their_password(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $target = User::factory()->twoFactorEnabled()->create();

        Livewire::actingAs($admin)
            ->test(ShowUser::class, ['user' => $target])
            ->call('callPanelAction', 'security', 'force-disable-2fa')
            ->assertSet('confirmingPassword', true)
            ->set('confirmablePassword', 'password')
            ->call('confirmPassword');

        $target->refresh();
        $this->assertNull($target->two_factor_secret);
        $this->assertNull($target->two_factor_recovery_codes);
        $this->assertNull($target->two_factor_confirmed_at);
    }

    public function test_force_disabling_two_factor_does_nothing_until_the_password_is_confirmed(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $target = User::factory()->twoFactorEnabled()->create();

        // Clicking the button only opens the password prompt — it must not touch the user yet.
        Livewire::actingAs($admin)
            ->test(ShowUser::class, ['user' => $target])
            ->call('callPanelAction', 'security', 'force-disable-2fa')
            ->assertSet('confirmingPassword', true);

        $target->refresh();
        $this->assertNotNull($target->two_factor_secret);
        $this->assertNotNull($target->two_factor_confirmed_at);
    }

    public function test_force_disabling_two_factor_is_rejected_with_the_wrong_password(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $target = User::factory()->twoFactorEnabled()->create();

        Livewire::actingAs($admin)
            ->test(ShowUser::class, ['user' => $target])
            ->call('callPanelAction', 'security', 'force-disable-2fa')
            ->set('confirmablePassword', 'wrong-password')
            ->call('confirmPassword')
            ->assertHasErrors('confirmablePassword');

        $target->refresh();
        $this->assertNotNull($target->two_factor_secret);
    }

    public function test_the_impersonate_action_is_hidden_when_not_permitted(): void
    {
        // A support user cannot impersonate a super admin.
        $support = User::factory()->support()->create();
        $target = User::factory()->superAdmin()->create();

        Livewire::actingAs($support)
            ->test(ShowUser::class, ['user' => $target])
            ->assertActionHidden('impersonate');
    }

    public function test_super_admins_can_assign_roles(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $target = User::factory()->create();
        Role::findOrCreate('Editor');

        Livewire::actingAs($admin)
            ->test(ShowUser::class, ['user' => $target])
            ->callAction('manageRoles', data: ['roles' => ['Editor']]);

        $this->assertTrue($target->fresh()->hasRole('Editor'));
    }

    public function test_super_admins_can_grant_super_admin_through_manage_roles_but_not_to_themselves(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $target = User::factory()->create();

        Livewire::actingAs($admin)
            ->test(ShowUser::class, ['user' => $target])
            ->callAction('manageRoles', data: ['roles' => [], 'is_super_admin' => true]);

        $this->assertTrue($target->fresh()->is_super_admin);

        // Managing your own access (roles or the super-admin flag) is never
        // offered — both abilities deny self, so the whole action is hidden.
        Livewire::actingAs($admin)
            ->test(ShowUser::class, ['user' => $admin])
            ->assertActionHidden('manageRoles');
    }

    public function test_super_admins_can_revoke_super_admin_from_another_user(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $target = User::factory()->superAdmin()->create();

        // The role list is hidden for a super-admin target (assignRole denies it),
        // so only the super-admin toggle is available here.
        Livewire::actingAs($admin)
            ->test(ShowUser::class, ['user' => $target])
            ->callAction('manageRoles', data: ['is_super_admin' => false]);

        $this->assertFalse($target->fresh()->is_super_admin);
    }

    /**
     * Regression for a reviewed vulnerability: a support-level actor must not be
     * able to assign an elevated role or grant super admin — the Manage Roles
     * action is hidden from the UI, and both abilities are denied.
     */
    public function test_support_cannot_assign_roles_or_grant_super_admin(): void
    {
        $support = User::factory()->support()->create();
        $target = User::factory()->create();
        Role::findOrCreate('Editor');

        Livewire::actingAs($support)
            ->test(ShowUser::class, ['user' => $target])
            ->assertActionHidden('manageRoles');

        $this->actingAs($support);

        $this->assertFalse(Gate::allows('assignRole', $target));
        $this->assertFalse(Gate::allows('grantSuperAdmin', $target));
        $this->assertFalse($target->fresh()->is_super_admin);
    }

    public function test_super_admins_can_directly_reset_a_users_password(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $target = User::factory()->create();

        Livewire::actingAs($admin)
            ->test(ShowUser::class, ['user' => $target])
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
            ->test(ShowUser::class, ['user' => $target])
            ->callAction('resetPassword');

        $this->assertSame($originalPassword, $target->fresh()->password);
        Notification::assertSentTo($target, ResetPassword::class);
    }

    public function test_the_demo_team_memberships_panel_renders(): void
    {
        $this->markTestSkipped('skipped because the demo panel is disabled in bootstrap/providers.php');

        $admin = User::factory()->superAdmin()->create();
        $target = User::factory()->create();

        Livewire::actingAs($admin)
            ->test(ShowUser::class, ['user' => $target])
            ->assertSee(__('admin.team_memberships'));
    }
}
