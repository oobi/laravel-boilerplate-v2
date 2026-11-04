<?php

declare(strict_types=1);

namespace Tests\Feature\Components;

use App\Livewire\Admin\Users\ListUsers;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TableScrollRailTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_table_gets_the_rail_with_named_arrows_between_its_rows_and_pagination(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        Livewire::test(ListUsers::class)
            ->assertSeeHtmlInOrder(['fi-ta-content-ctn', 'tableScrollRail(', 'fi-pagination'])
            ->assertSeeHtml('aria-label="Scroll table left"')
            ->assertSeeHtml('aria-label="Scroll table right"');
    }
}
