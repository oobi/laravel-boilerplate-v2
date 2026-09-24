<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class HomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_see_login_and_register_links(): void
    {
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
}
