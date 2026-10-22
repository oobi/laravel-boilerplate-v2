<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Support\PageTitle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every page names itself in the browser tab, "Page · App name", so tabs,
 * history, bookmarks and screen readers can tell them apart (WCAG 2.4.2,
 * GitHub #30).
 */
class PageTitleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Compares the raw <title>, so a value escaped twice (O&amp;#039;Brien) fails.
     */
    private function assertTitled(string $expected, string $url): void
    {
        preg_match('/<title>(.*?)<\/title>/s', $this->get($url)->assertOk()->getContent(), $matches);

        $this->assertSame(e($expected.' · '.config('app.name')), trim($matches[1] ?? ''), $url);
    }

    public function test_admin_pages_name_the_page_then_what_it_sits_under(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());
        $user = User::factory()->create(['first_name' => 'Siobhan', 'last_name' => "O'Brien & Co"]);

        $this->assertTitled(__('admin.users'), route('users.index'));
        $this->assertTitled(__('admin.create').' · '.__('admin.users'), route('users.create'));
        $this->assertTitled("Siobhan O'Brien & Co · ".__('admin.users'), route('users.show', $user));
        $this->assertTitled(__('admin.edit')." · Siobhan O'Brien & Co · ".__('admin.users'), route('users.edit', $user));
        $this->assertTitled(__('admin.roles'), route('roles.index'));
        $this->assertTitled('Lead · '.__('admin.roles'), route('roles.edit', Role::create(['name' => 'Lead'])));
        $this->assertTitled(__('admin.password').' · '.__('admin.my_profile'), route('profile.password'));
    }

    public function test_an_edited_record_is_a_linked_breadcrumb(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());
        $user = User::factory()->create(['first_name' => 'Siobhan', 'last_name' => "O'Brien"]);

        $this->get(route('users.edit', $user))
            ->assertSeeHtml('href="'.route('users.show', $user).'"')
            ->assertSee("Siobhan O'Brien");
    }

    public function test_sign_in_pages_name_themselves(): void
    {
        $this->assertTitled(__('Log in'), route('login'));
        $this->assertTitled(__('Forgot your password?'), route('password.request'));
    }

    public function test_error_pages_name_the_error(): void
    {
        preg_match('/<title>(.*?)<\/title>/s', $this->get('/no-such-page')->assertNotFound()->getContent(), $matches);

        $this->assertSame(__('Page not found').' · '.config('app.name'), $matches[1] ?? '');
    }

    public function test_a_page_without_a_title_falls_back_to_the_app_name(): void
    {
        $this->assertSame(config('app.name'), PageTitle::for());
        $this->assertSame('Users · '.config('app.name'), PageTitle::for('Users', [null, '']));
    }
}
