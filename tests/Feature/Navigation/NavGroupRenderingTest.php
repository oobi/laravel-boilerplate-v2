<?php

declare(strict_types=1);

namespace Tests\Feature\Navigation;

use App\Models\User;
use App\Support\Navigation\NavGroup;
use App\Support\Navigation\NavItem;
use App\Support\Navigation\NavRegistry;
use Illuminate\Contracts\Auth\Authenticatable;
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

    public function test_iconless_groups_render_with_a_separator(): void
    {
        NavRegistry::extend(IconlessTestNavGroup::class);

        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSeeInOrder([
                'Management',
                'border-t border-base-300',
                'Iconless test group',
            ], false);
    }
}

class IconlessTestNavGroup implements NavGroup
{
    public function label(): string
    {
        return 'Iconless test group';
    }

    public function icon(): ?string
    {
        return null;
    }

    public function items(): array
    {
        return [new NavItem('Dashboard', 'dashboard', 'heroicon-o-home')];
    }

    public function order(): int
    {
        return 0;
    }

    public function visible(?Authenticatable $viewer): bool
    {
        return true;
    }
}
