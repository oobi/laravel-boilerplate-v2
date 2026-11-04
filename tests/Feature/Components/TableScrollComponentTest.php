<?php

declare(strict_types=1);

namespace Tests\Feature\Components;

use Tests\TestCase;

class TableScrollComponentTest extends TestCase
{
    public function test_the_table_scrolls_inside_it_with_the_rail_then_the_footer_below(): void
    {
        $this->blade('<x-table-scroll class="mt-4"><table class="table"></table><x-slot:footer><footer>Pages</footer></x-slot:footer></x-table-scroll>')
            ->assertSee('ui-table-main mt-4', false)
            ->assertSeeInOrder(['ui-table-scroller', '<table class="table">', 'tableScrollRail(', 'aria-label="Scroll table right"', '<footer>Pages</footer>'], false);
    }
}
