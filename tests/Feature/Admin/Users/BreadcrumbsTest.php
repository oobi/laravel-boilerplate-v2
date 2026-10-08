<?php

namespace Tests\Feature\Admin\Users;

use App\Models\User;
use App\Support\Breadcrumbs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

class BreadcrumbsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_users_index_breadcrumb_names_the_list_once_without_linking_to_itself(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $response = $this->actingAs($admin)->get('/admin/users');

        $response->assertDontSee('<a href="'.route('users.index').'" class="hover:text-base-content">', false);
        $response->assertSee('<span>Users</span>', false);
    }

    public function test_the_users_edit_breadcrumb_links_back_to_the_users_index(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $target = User::factory()->create();

        $response = $this->actingAs($admin)->get("/admin/users/{$target->id}/edit");

        $response->assertSee(
            '<a href="'.route('users.index').'" class="hover:text-base-content">Users</a>',
            false,
        );
        $response->assertSeeInOrder(['Users', $target->name, 'Edit']);
    }

    public function test_the_users_create_breadcrumb_links_back_to_the_users_index(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $response = $this->actingAs($admin)->get('/admin/users/create');

        $response->assertSee(
            '<a href="'.route('users.index').'" class="hover:text-base-content">Users</a>',
            false,
        );
        $response->assertSeeInOrder(['Users', 'Create']);
    }

    public function test_pages_without_a_sibling_index_route_render_a_single_crumb(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/admin/dashboard');

        $response->assertDontSee(
            '<a href="'.route('users.index').'" class="hover:text-base-content">',
            false,
        );
    }

    public function test_an_index_page_with_its_own_title_keeps_its_resource_crumb_unlinked(): void
    {
        Route::get('/widgets', function (): array {
            View::startSection('page-title', 'Widgets over time');

            return Breadcrumbs::trail();
        })->name('widgets.index');
        Route::getRoutes()->refreshNameLookups();

        $this->get('/widgets')->assertExactJson([
            ['label' => __('admin.breadcrumb_root'), 'url' => null],
            ['label' => 'Widgets', 'url' => null],
            ['label' => 'Widgets over time', 'url' => null],
        ]);
    }
}
