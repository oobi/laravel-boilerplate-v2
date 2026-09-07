<?php

namespace Tests\Feature\Admin\Users;

use App\Livewire\Admin\Users\ShowUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
