<?php

namespace Tests\Feature\Profile;

use App\Enums\SystemPermission;
use App\Http\Middleware\RememberAccountArea;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The profile renders in the shell of the area the user was last in
 * (App\Support\AccountArea\AccountAreaRegistry), falling back to the sidebar-less
 * shell when they have none or can no longer enter it. The admin sidebar's
 * Users link marks the admin shell.
 */
class ProfileAreaShellTest extends TestCase
{
    use RefreshDatabase;

    /** The sidebar-less shell's form-width column. */
    private const string BARE_SHELL = 'ui-page flex flex-col gap-4 max-w-3xl';

    public function test_the_profile_is_sidebar_less_when_no_area_was_visited(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->get(route('profile.edit'))
            ->assertOk()
            ->assertDontSee(route('users.index'))
            ->assertSee(self::BARE_SHELL, false);
    }

    public function test_the_profile_keeps_the_admin_shell_after_an_admin_page(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->get(route('dashboard'))->assertOk();

        $this->get(route('profile.password'))
            ->assertOk()
            ->assertSee(route('users.index'))
            ->assertDontSee(self::BARE_SHELL, false)
            // In an area's shell the page sits under "My Profile", in the trail and the tab title.
            ->assertSeeInOrder([__('admin.breadcrumb_root'), __('admin.my_profile'), __('admin.password')])
            ->assertSee('<title>'.e(__('admin.password').' · '.__('admin.my_profile')), false);
    }

    public function test_the_profile_drops_the_admin_shell_once_admin_access_is_gone(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->get(route('dashboard'))->assertOk();
        $admin->forceFill(['is_super_admin' => false])->save();

        $this->get(route('profile.edit'))
            ->assertOk()
            ->assertDontSee(route('users.index'))
            ->assertSee(self::BARE_SHELL, false);
    }

    /** Area routes all need `verified`, so an unverified user's sidebar would only lead to the verify notice. */
    public function test_an_unverified_user_gets_the_sidebar_less_shell(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->get(route('dashboard'))->assertOk();
        $admin->forceFill(['email_verified_at' => null])->save();

        $this->get(route('profile.edit'))
            ->assertOk()
            ->assertDontSee(route('users.index'))
            ->assertSee(self::BARE_SHELL, false);
    }

    /** A remembered area nothing resolves any more (e.g. a removed add-on) falls back quietly. */
    public function test_an_unknown_remembered_area_falls_back_to_the_sidebar_less_shell(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->withSession(['account_area' => ['area' => 'removed-add-on', 'id' => '1']])
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertDontSee(route('users.index'))
            ->assertSee(self::BARE_SHELL, false);
    }

    /** A new admin route group that forgets the middleware would drop users out of the admin shell. */
    public function test_every_admin_route_remembers_the_admin_area(): void
    {
        $adminRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn (RoutingRoute $route): bool => in_array('can:'.SystemPermission::ACCESS_ADMIN_PANEL->value, $route->gatherMiddleware(), true));

        $this->assertNotEmpty($adminRoutes);

        $adminRoutes->each(fn (RoutingRoute $route) => $this->assertContains(
            RememberAccountArea::class.':admin',
            $route->gatherMiddleware(),
            "Admin route [{$route->uri()}] doesn't remember the admin area.",
        ));
    }
}
