<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\ShowUser;
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

        $this->actingAs($user)->get("/users/{$other->id}")->assertForbidden();
    }

    public function test_admins_can_view_a_user(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $target = User::factory()->create(['first_name' => 'Jane', 'last_name' => 'Doe']);

        $response = $this->actingAs($admin)->get("/users/{$target->id}");

        $response->assertStatus(200);
        $response->assertSee('Jane Doe');
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

    public function test_admins_can_force_disable_a_users_two_factor_authentication(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $target = User::factory()->twoFactorEnabled()->create();

        Livewire::actingAs($admin)
            ->test(ShowUser::class, ['user' => $target])
            ->call('callPanelAction', 'security', 'force-disable-2fa');

        $target->refresh();
        $this->assertNull($target->two_factor_secret);
        $this->assertNull($target->two_factor_recovery_codes);
        $this->assertNull($target->two_factor_confirmed_at);
    }

    public function test_the_demo_team_memberships_panel_renders(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $target = User::factory()->create();

        Livewire::actingAs($admin)
            ->test(ShowUser::class, ['user' => $target])
            ->assertSee(__('admin.team_memberships'));
    }
}
