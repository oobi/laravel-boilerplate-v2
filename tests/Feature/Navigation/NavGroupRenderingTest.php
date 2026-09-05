<?php

declare(strict_types=1);

namespace Tests\Feature\Navigation;

use App\Models\User;
use App\Support\Navigation\Registry\NavItem;
use App\Support\Navigation\Registry\NavRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavGroupRenderingTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        NavRegistry::flush();

        parent::tearDown();
    }

    public function test_registered_groups_render_with_a_separator_and_label(): void
    {
        NavRegistry::group('test-group')
            ->label('Test Group')
            ->add(NavItem::make('test-item')->label('Test Item')->route('dashboard')->icon('heroicon-o-home'));

        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSeeInOrder([
                'Management',
                'border-t border-base-300',
                'Test Group',
                'Test Item',
            ], false);
    }

    public function test_item_level_permission_hides_only_that_item(): void
    {
        NavRegistry::group('test-group')
            ->label('Test Group')
            ->add(
                NavItem::make('visible-item')->label('Visible Item')->route('dashboard')->icon('heroicon-o-home'),
                NavItem::make('gated-item')->label('Gated Item')->route('dashboard')->icon('heroicon-o-home')->can('non-existent permission'),
            );

        // A super admin bypasses every ability by design, so gating-denial needs a non-super-admin actor here.
        $support = User::factory()->support()->create();

        $this->actingAs($support)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Visible Item')
            ->assertDontSee('Gated Item');
    }

    public function test_group_level_permission_hides_the_whole_group(): void
    {
        NavRegistry::group('test-group')
            ->label('Gated Group')
            ->can('non-existent permission')
            ->add(NavItem::make('test-item')->label('Test Item')->route('dashboard')->icon('heroicon-o-home'));

        // A super admin bypasses every ability by design, so gating-denial needs a non-super-admin actor here.
        $support = User::factory()->support()->create();

        $this->actingAs($support)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Gated Group');
    }

    public function test_addon_can_append_an_item_to_an_existing_group(): void
    {
        NavRegistry::group('management')->add(
            NavItem::make('addon-item')->label('Addon Item')->route('dashboard')->icon('heroicon-o-home'),
        );

        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Addon Item');
    }

    public function test_group_opens_when_any_of_its_items_routes_is_active(): void
    {
        NavRegistry::group('test-group')
            ->label('Test Group')
            ->add(
                NavItem::make('users-link')->label('Users Link')->route('users.index')->icon('heroicon-o-user')->active('users.*'),
                NavItem::make('dashboard-link')->label('Dashboard Link')->route('dashboard')->icon('heroicon-o-home'),
            );

        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->get(route('users.index'))
            ->assertOk()
            ->assertDontSee("\$persist(true).as('nav-test-group')", false);
    }
}
