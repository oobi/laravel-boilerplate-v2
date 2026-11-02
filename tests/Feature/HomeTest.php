<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AccountMenu\AccountMenuRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use Tests\TestCase;

class HomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_see_login_and_register_links(): void
    {
        $this->skipUnlessFortifyFeature(Features::registration());

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee(__('Log in'));
        $response->assertSee(route('login'), false);
        $response->assertSee(__('Log in or register to get started.'));
        $response->assertSee(route('register'), false);
    }

    public function test_guests_are_not_invited_to_register_when_registration_is_disabled(): void
    {
        $routes = new RouteCollection;

        foreach (Route::getRoutes() as $route) {
            if ($route->getName() !== 'register') {
                $routes->add($route);
            }
        }

        Route::setRoutes($routes);

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee(__('Log in to get started.'));
        $response->assertDontSee(__('Log in or register to get started.'));
    }

    public function test_authenticated_users_see_a_welcome_message_and_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/');

        $response->assertStatus(200);
        $response->assertSee($user->first_name);
        $response->assertSee(route('logout'), false);
        $response->assertDontSee(route('dashboard'), false);
    }

    public function test_users_with_a_system_role_see_a_link_to_the_admin_dashboard(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $response = $this->actingAs($admin)->get('/');

        $response->assertStatus(200);
        $response->assertSee(route('dashboard'), false);
    }

    /** The profile is every signed-in user's, not only admins'. */
    public function test_any_signed_in_user_gets_a_my_profile_link(): void
    {
        $html = $this->actingAs(User::factory()->create())->get('/')
            ->assertOk()
            ->assertDontSee(route('dashboard'), false)
            ->getContent();

        $this->assertMatchesRegularExpression('#href="'.preg_quote(route('profile.edit'), '#').'"[^>]*>\s*'.preg_quote(__('admin.my_profile'), '#').'\s*</a>#', $html);
    }

    /** On a phone the nav's links move into the avatar menu, Admin dashboard included for an admin. */
    public function test_the_account_menu_carries_the_admin_dashboard_link_for_admins_only(): void
    {
        AccountMenuRegistry::flush();

        $adminMenu = $this->accountMenu($this->actingAs(User::factory()->superAdmin()->create())->get('/')->assertOk()->getContent());
        $this->assertSame(1, substr_count($adminMenu, 'href="'.route('dashboard').'"'));
        $this->assertStringContainsString('href="'.route('profile.edit').'"', $adminMenu);

        $userMenu = $this->accountMenu($this->actingAs(User::factory()->create())->get('/')->assertOk()->getContent());
        $this->assertStringNotContainsString('href="'.route('dashboard').'"', $userMenu);
    }

    /** An add-on's own Admin dashboard link (the teams tier has one) isn't doubled. */
    public function test_the_account_menu_does_not_repeat_an_add_on_admin_dashboard_link(): void
    {
        AccountMenuRegistry::flush();
        AccountMenuRegistry::item('add-on-dashboard')->label('Admin dashboard')->url(fn (): string => route('dashboard'));

        $menu = $this->accountMenu($this->actingAs(User::factory()->superAdmin()->create())->get('/')->assertOk()->getContent());

        $this->assertSame(1, substr_count($menu, 'href="'.route('dashboard').'"'));
    }

    private function accountMenu(string $html): string
    {
        $this->assertMatchesRegularExpression('#<ul id="account-menu".*?</ul>#s', $html);
        preg_match('#<ul id="account-menu".*?</ul>#s', $html, $match);

        return $match[0];
    }
}
