<?php

namespace Tests\Feature\Admin\Users;

use App\Enums\SystemPermission;
use App\Enums\UserAbility;
use App\Livewire\Admin\Users\ShowUser;
use App\Models\Role;
use App\Models\User;
use App\Support\TwoFactor\GracePeriod;
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

    public function test_view_users_opens_a_profile_without_the_edit_button(): void
    {
        $viewer = User::factory()->withPermission(SystemPermission::VIEW_USERS)->create();
        $target = User::factory()->create(['first_name' => 'Jane', 'last_name' => 'Doe']);

        $this->actingAs($viewer)->get("/admin/users/{$target->id}")
            ->assertOk()
            ->assertSee('Jane Doe')
            ->assertDontSee(route('users.edit', $target));
    }

    public function test_panel_entry_alone_does_not_open_a_profile(): void
    {
        $panelOnly = User::factory()->withPermission(SystemPermission::ACCESS_ADMIN_PANEL)->create();
        $target = User::factory()->create();

        $this->actingAs($panelOnly)->get("/admin/users/{$target->id}")->assertForbidden();
    }

    public function test_admins_can_view_a_user(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $target = User::factory()->create(['first_name' => 'Jane', 'last_name' => 'Doe']);

        $response = $this->actingAs($admin)->get("/admin/users/{$target->id}");

        $response->assertStatus(200);
        $response->assertSee('Jane Doe');
    }

    public function test_a_users_name_is_escaped_in_the_page_header(): void
    {
        // Regression: names are user-supplied at registration, so the page-header
        // must escape them rather than render raw HTML (stored XSS otherwise).
        $admin = User::factory()->superAdmin()->create();
        $target = User::factory()->create([
            'first_name' => '<script>alert(1)</script>',
            'last_name' => 'Doe',
        ]);

        $this->actingAs($admin)->get("/admin/users/{$target->id}")
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
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

    public function test_a_locked_out_user_shows_as_locked_out_with_a_reset_button(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $target = User::factory()->graceStartedDaysAgo(20)->create();
        $target->assignRole(Role::create(['name' => 'Admin', 'requires_two_factor' => true]));

        Livewire::actingAs($admin)
            ->test(ShowUser::class, ['user' => $target])
            ->assertSee(__('admin.two_factor_grace_locked_out'))
            ->assertSee(__('admin.reset_two_factor_grace'));
    }

    public function test_resetting_the_grace_period_after_confirming_the_password_lifts_the_lockout(): void
    {
        $admin = User::factory()->withPermission(SystemPermission::VIEW_USERS, SystemPermission::MANAGE_USERS)->create();
        $target = User::factory()->graceStartedDaysAgo(20)->create();
        $target->assignRole(Role::create(['name' => 'Admin', 'requires_two_factor' => true]));

        Livewire::actingAs($admin)
            ->test(ShowUser::class, ['user' => $target])
            ->call('callPanelAction', 'security', 'reset-2fa-grace')
            ->assertSet('confirmingPassword', true)
            ->set('confirmablePassword', 'password')
            ->call('confirmPassword');

        $target->refresh();
        $this->assertTrue($target->two_factor_grace_started_at->isToday());
        $this->assertFalse(GracePeriod::locksOut($target));
    }

    public function test_resetting_the_grace_period_is_forbidden_without_manage_users(): void
    {
        $viewer = User::factory()->withPermission(SystemPermission::VIEW_USERS)->create();
        $target = User::factory()->graceStartedDaysAgo(20)->create();
        $target->assignRole(Role::create(['name' => 'Admin', 'requires_two_factor' => true]));

        Livewire::actingAs($viewer)
            ->test(ShowUser::class, ['user' => $target])
            ->assertDontSee(__('admin.reset_two_factor_grace'))
            ->call('callPanelAction', 'security', 'reset-2fa-grace')
            ->set('confirmablePassword', 'password')
            ->call('confirmPassword')
            ->assertForbidden();

        $this->assertTrue(GracePeriod::locksOut($target->fresh()));
    }

    public function test_a_super_admin_without_two_factor_shows_as_never_locked_out_with_no_reset(): void
    {
        $admin = User::factory()->superAdmin()->create();
        Role::create(['name' => 'Admin', 'requires_two_factor' => true]);
        $target = User::factory()->superAdmin()->graceStartedDaysAgo(20)->create();

        Livewire::actingAs($admin)
            ->test(ShowUser::class, ['user' => $target])
            ->assertSee(__('admin.two_factor_grace_super_admin'))
            ->assertDontSee(__('admin.reset_two_factor_grace'));
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

    public function test_manage_roles_offers_and_accepts_system_roles_only(): void
    {
        // An add-on's scoped roles (a team role, say) share the table but mean
        // nothing at the system scope — never offer one, never store one.
        $admin = User::factory()->superAdmin()->create();
        $target = User::factory()->create();
        Role::findOrCreate('Editor');
        Role::query()->create(['name' => 'Scoped Role', 'scope' => 'team']);

        // Not an option, so the checkbox list's own validation rejects it.
        Livewire::actingAs($admin)
            ->test(ShowUser::class, ['user' => $target])
            ->callAction('manageRoles', data: ['roles' => ['Scoped Role']])
            ->assertHasActionErrors();

        $this->assertFalse($target->fresh()->hasRole('Scoped Role'));

        Livewire::actingAs($admin)
            ->test(ShowUser::class, ['user' => $target])
            ->callAction('manageRoles', data: ['roles' => ['Editor']])
            ->assertHasNoActionErrors();

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

        $this->assertFalse(Gate::allows(UserAbility::ASSIGN_ROLE, $target));
        $this->assertFalse(Gate::allows(UserAbility::GRANT_SUPER_ADMIN, $target));
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
