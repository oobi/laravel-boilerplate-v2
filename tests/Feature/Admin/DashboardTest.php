<?php

namespace Tests\Feature\Admin;

use App\Enums\SystemPermission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/admin/dashboard')->assertRedirect('/login');
    }

    public function test_the_admin_root_redirects_an_admin_to_the_dashboard(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->get('/admin')->assertRedirect(route('dashboard'));
    }

    public function test_the_admin_root_is_gated_like_the_dashboard(): void
    {
        // A guest goes to login (never a form on the admin host in host mode); a
        // signed-in non-admin is forbidden — the root is never an open door.
        $this->get('/admin')->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
    }

    public function test_users_without_a_system_role_cannot_view_the_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/admin/dashboard')->assertForbidden();
    }

    public function test_panel_entry_alone_shows_the_dashboard_without_the_user_roster(): void
    {
        // `access admin panel` is entry only: the roster (names, addresses) is the users area's data.
        $panelOnly = User::factory()->withPermission(SystemPermission::ACCESS_ADMIN_PANEL)->create();
        $someone = User::factory()->create(['first_name' => 'Roster', 'last_name' => 'Person']);

        $this->actingAs($panelOnly)->get('/admin/dashboard')
            ->assertOk()
            ->assertSee(__('Total Users'))
            ->assertDontSee(__('Recent Users'))
            ->assertDontSee('Roster Person')
            ->assertDontSee(route('users.index'));
    }

    public function test_view_users_shows_the_recent_users_card(): void
    {
        $viewer = User::factory()->withPermission(SystemPermission::VIEW_USERS)->create();
        User::factory()->create(['first_name' => 'Roster', 'last_name' => 'Person']);

        $this->actingAs($viewer)->get('/admin/dashboard')
            ->assertOk()
            ->assertSee(__('Recent Users'))
            ->assertSee('Roster Person');
    }

    public function test_users_with_a_system_role_can_view_the_dashboard(): void
    {
        $user = User::factory()->superAdmin()->create();

        $response = $this->actingAs($user)->get('/admin/dashboard');

        $response->assertStatus(200);
        $response->assertSee($user->name);
    }
}
