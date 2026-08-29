<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BreadcrumbsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_users_index_breadcrumb_shows_the_resource_and_current_crumb(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $response = $this->actingAs($admin)->get('/users');

        $response->assertSee(
            '<a href="'.route('users.index').'" class="hover:text-base-content">Users</a>',
            false,
        );
        $response->assertSeeInOrder(['Users', 'List']);
    }

    public function test_the_users_edit_breadcrumb_links_back_to_the_users_index(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $target = User::factory()->create();

        $response = $this->actingAs($admin)->get("/users/{$target->id}/edit");

        $response->assertSee(
            '<a href="'.route('users.index').'" class="hover:text-base-content">Users</a>',
            false,
        );
        $response->assertSeeInOrder(['Users', 'Edit']);
    }

    public function test_the_users_create_breadcrumb_links_back_to_the_users_index(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $response = $this->actingAs($admin)->get('/users/create');

        $response->assertSee(
            '<a href="'.route('users.index').'" class="hover:text-base-content">Users</a>',
            false,
        );
        $response->assertSeeInOrder(['Users', 'Create']);
    }

    public function test_pages_without_a_sibling_index_route_render_a_single_crumb(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertDontSee(
            '<a href="'.route('users.index').'" class="hover:text-base-content">',
            false,
        );
    }
}
