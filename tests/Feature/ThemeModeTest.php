<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every layout's <html> carries the theme cookie the same way
 * (App\Support\Theme\ThemeMode).
 */
class ThemeModeTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_theme_cookie_sets_the_page_theme(): void
    {
        $this->withUnencryptedCookie('theme', 'dark')->get(route('login'))
            ->assertSee('data-theme="boilerplate-dark" data-theme-mode="dark"', false);

        $this->withUnencryptedCookie('theme', 'light')->get(route('login'))
            ->assertSee('data-theme="boilerplate" data-theme-mode="light"', false);
    }

    public function test_no_cookie_or_an_unknown_value_is_auto(): void
    {
        $this->get(route('login'))->assertSee('data-theme-mode="auto"', false);

        $this->withUnencryptedCookie('theme', '"><script>')->get(route('login'))
            ->assertSee('data-theme="boilerplate" data-theme-mode="auto"', false)
            ->assertDontSee('"><script>', false);
    }

    public function test_every_layout_carries_it_the_same_way(): void
    {
        // The error layout (a 404) and the app shell (any admin page).
        $this->withUnencryptedCookie('theme', 'dark')->get('/no-such-page-anywhere')
            ->assertSee('data-theme="boilerplate-dark" data-theme-mode="dark"', false);

        $this->actingAs(User::factory()->superAdmin()->create())
            ->withUnencryptedCookie('theme', 'dark')->get(route('dashboard'))
            ->assertSee('data-theme="boilerplate-dark" data-theme-mode="dark"', false);
    }
}
