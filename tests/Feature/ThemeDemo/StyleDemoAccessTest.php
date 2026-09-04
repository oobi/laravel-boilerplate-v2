<?php

namespace Tests\Feature\ThemeDemo;

use App\Models\User;
use Concise\ThemeDemo\Livewire\Tables\FilamentTable;
use Concise\ThemeDemo\Livewire\Tables\MaximalistTable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StyleDemoAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/admin/style-demo')->assertRedirect('/login');
    }

    public function test_users_without_admin_access_are_forbidden(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/admin/style-demo')->assertForbidden();
    }

    #[DataProvider('stylePageProvider')]
    public function test_admins_can_view_every_style_demo_page(string $uri): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->get($uri)->assertStatus(200);
    }

    public function test_table_tabs_are_connected_to_their_content_panel(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->get('/admin/style-demo/tables/empty')
            ->assertOk()
            ->assertSee('tabs-connected overflow-hidden rounded-box border border-base-300 bg-base-100', false)
            ->assertSee('tabs-connected__header', false)
            ->assertSee('tabs tabs-border gap-2 sm:gap-6', false)
            ->assertSee('tabs-connected__divider', false)
            ->assertSee('tab-content tabs-connected__content bg-base-100 p-6', false);
    }

    #[DataProvider('tabContentProvider')]
    public function test_tab_content_variants_render_their_comparison_sample(string $uri, string $expectedContent): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->get($uri)
            ->assertOk()
            ->assertSee('tabs-connected__content', false)
            ->assertSee($expectedContent);
    }

    public function test_admins_can_sort_filament_table_by_joined_at(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin);

        Livewire::test(FilamentTable::class, ['variant' => 'maximalist'])
            ->call('sortTable', 'joined_at')
            ->assertSet('tableSort', 'joined_at:asc');
    }

    public function test_maximalist_daisy_table_uses_filament_pagination_content(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin);

        Livewire::test(MaximalistTable::class)
            ->assertSee('Showing 1 to 10 of 25 results')
            ->assertSee('Per page')
            ->assertSeeHtml('wire:model.live="perPage"')
            ->set('perPage', 25)
            ->assertSee('Showing 1 to 25 of 25 results');
    }

    /** @return array<string, array{string}> */
    public static function stylePageProvider(): array
    {
        return [
            'overview' => ['/admin/style-demo'],
            'empty table' => ['/admin/style-demo/tables/empty'],
            'simple table' => ['/admin/style-demo/tables/simple'],
            'maximalist table' => ['/admin/style-demo/tables/maximalist'],
            'wide table' => ['/admin/style-demo/tables/wide'],
            'filament table empty' => ['/admin/style-demo/tables/filament/empty'],
            'filament table simple' => ['/admin/style-demo/tables/filament/simple'],
            'filament table maximalist' => ['/admin/style-demo/tables/filament/maximalist'],
            'filament table custom header' => ['/admin/style-demo/tables/filament/custom-header'],
            'filament table wide' => ['/admin/style-demo/tables/filament/wide'],
            'daisy form' => ['/admin/style-demo/forms/daisy'],
            'daisy form standard' => ['/admin/style-demo/forms/daisy/standard'],
            'filament form' => ['/admin/style-demo/forms/filament'],
            'components' => ['/admin/style-demo/components'],
            'filament components' => ['/admin/style-demo/components/filament'],
            'tab content table' => ['/admin/style-demo/tab-content/table'],
            'tab content form' => ['/admin/style-demo/tab-content/form'],
            'tab content panels' => ['/admin/style-demo/tab-content/panels'],
            'tab content text' => ['/admin/style-demo/tab-content/text'],
        ];
    }

    /** @return array<string, array{string, string}> */
    public static function tabContentProvider(): array
    {
        return [
            'table' => ['/admin/style-demo/tab-content/table', 'Ava Thompson'],
            'form' => ['/admin/style-demo/tab-content/form', 'Save changes'],
            'panels' => ['/admin/style-demo/tab-content/panels', 'Recent activity'],
            'plain text' => ['/admin/style-demo/tab-content/text', 'Keeping a shared workspace clear'],
        ];
    }
}
