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
        $this->get('/style-demo')->assertRedirect('/login');
    }

    public function test_users_without_admin_access_are_forbidden(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/style-demo')->assertForbidden();
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
            ->get('/style-demo/tables/empty')
            ->assertOk()
            ->assertSee('tabs-connected overflow-hidden rounded-box border border-base-300 bg-base-100', false)
            ->assertSee('tabs-connected__header', false)
            ->assertSee('tabs tabs-border gap-2 sm:gap-8', false)
            ->assertSee('tabs-connected__divider', false)
            ->assertSee('tab-content tabs-connected__content bg-base-100 p-6', false);
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
            'overview' => ['/style-demo'],
            'empty table' => ['/style-demo/tables/empty'],
            'simple table' => ['/style-demo/tables/simple'],
            'maximalist table' => ['/style-demo/tables/maximalist'],
            'wide table' => ['/style-demo/tables/wide'],
            'filament table empty' => ['/style-demo/tables/filament/empty'],
            'filament table simple' => ['/style-demo/tables/filament/simple'],
            'filament table maximalist' => ['/style-demo/tables/filament/maximalist'],
            'filament table custom header' => ['/style-demo/tables/filament/custom-header'],
            'filament table wide' => ['/style-demo/tables/filament/wide'],
            'daisy form' => ['/style-demo/forms/daisy'],
            'filament form' => ['/style-demo/forms/filament'],
            'components' => ['/style-demo/components'],
        ];
    }
}
