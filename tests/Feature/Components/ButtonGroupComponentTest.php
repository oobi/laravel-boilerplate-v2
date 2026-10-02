<?php

declare(strict_types=1);

namespace Tests\Feature\Components;

use Tests\TestCase;

class ButtonGroupComponentTest extends TestCase
{
    public function test_a_group_is_labelled_and_says_which_segment_is_chosen(): void
    {
        $this->blade('<x-button-group label="Status"><x-button-group.item active>All</x-button-group.item><x-button-group.item>Archived</x-button-group.item></x-button-group>')
            ->assertSeeInOrder(['role="group"', 'aria-label="Status"'], false)
            ->assertSee('aria-pressed="true" class="ui-button-group-btn ui-button-group-btn-active-primary"', false)
            ->assertSee('aria-pressed="false" class="ui-button-group-btn"', false);
    }

    public function test_the_active_colour_and_layout_options(): void
    {
        $this->blade('<x-button-group block quiet size="sm"><x-button-group.item active color="error">Bin</x-button-group.item><x-button-group.item active color="neutral">Dark</x-button-group.item></x-button-group>')
            ->assertSee('ui-button-group ui-button-group-block ui-button-group-quiet ui-button-group-sm', false)
            ->assertSee('ui-button-group-btn-active-danger', false)
            ->assertSee('ui-button-group-btn-active-neutral', false);
    }

    public function test_a_segment_with_href_is_a_link_marked_current(): void
    {
        $this->blade('<x-button-group><x-button-group.item href="/page/2" active>2</x-button-group.item></x-button-group>')
            ->assertSeeInOrder(['<a href="/page/2"', 'aria-current="true"'], false)
            ->assertDontSee('aria-pressed', false);
    }
}
