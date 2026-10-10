<?php

namespace Tests\Feature\Accessibility;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Landmarks, names and states a screen reader relies on (oobi #34).
 */
class LandmarksAndLabelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_pages_have_a_skip_link_main_breadcrumb_nav_and_named_account_menu(): void
    {
        $response = $this->actingAs(User::factory()->superAdmin()->create())->get(route('users.index'));

        $response->assertOk()
            ->assertSeeInOrder(['href="#main-content"', 'Skip to content'], false)
            ->assertSee('<main id="main-content"', false)
            ->assertSee('aria-label="Account menu"', false)
            ->assertSee('aria-controls="account-menu"', false)
            ->assertSee('aria-expanded="false"', false)
            ->assertDontSee('role="button"', false);

        // The sidebar groups say whether they're open and what they open, with ids made in the
        // browser (x-id), since the shell draws the nav twice: no server-side id to repeat.
        $html = $response->getContent();
        $this->assertStringContainsString('x-id="[\'nav-group\']"', $html);
        $this->assertStringContainsString(':aria-controls="$id(\'nav-group\')"', $html);

        // The breadcrumb marks the current page, and its root is hidden on phones (no stray "inline").
        $this->assertSame(1, preg_match('#<nav aria-label="Breadcrumb".*?</nav>#s', $html, $crumbs));
        $this->assertStringContainsString('aria-current="page"', $crumbs[0]);
        $this->assertStringContainsString('<li class="hidden sm:inline">', $crumbs[0]);

        // Every id on the page is unique.
        preg_match_all('/\sid="([^"]+)"/', $html, $ids);
        $this->assertSame([], array_keys(array_filter(array_count_values($ids[1]), fn (int $count): bool => $count > 1)), 'duplicate ids');
    }

    public function test_the_current_page_is_marked_in_the_sidebar(): void
    {
        $html = $this->actingAs(User::factory()->superAdmin()->create())->get(route('users.index'))->getContent();

        $this->assertMatchesRegularExpression('#href="[^"]*/users" class="nav-item nav-item-active"\s+aria-current="page"#', $html);
        $this->assertDoesNotMatchRegularExpression('#href="[^"]*/roles" class="nav-item\s*"\s+aria-current#', $html);
    }

    public function test_guest_pages_have_a_main_landmark_and_one_autofocus(): void
    {
        $this->get(route('login'))->assertSee('<main', false);

        if (Route::has('register')) {
            $this->assertSame(1, substr_count($this->get(route('register'))->getContent(), 'autofocus'));
        }
    }

    public function test_alerts_interrupt_only_for_errors_and_warnings(): void
    {
        $this->blade('<x-alert color="success">Saved.</x-alert>')->assertSee('role="status"', false);
        $this->blade('<x-alert color="info">Note.</x-alert>')->assertSee('role="status"', false);
        $this->blade('<x-alert color="error">Failed.</x-alert>')->assertSee('role="alert"', false);
        $this->blade('<x-alert color="warning">Careful.</x-alert>')->assertSee('role="alert"', false);
        $this->blade('<x-alert color="success" role="alert">Saved.</x-alert>')->assertSee('role="alert"', false)->assertDontSee('role="status"', false);
    }

    public function test_the_trash_toggle_names_its_buttons_in_words(): void
    {
        $this->blade('<x-table-trash-toggle :active-count="106" :trashed-count="1" />')
            ->assertSee('aria-label="106 active records"', false)
            ->assertSee('aria-label="1 in the trash"', false);
    }
}
