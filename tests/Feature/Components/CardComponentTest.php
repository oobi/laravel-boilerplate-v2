<?php

declare(strict_types=1);

namespace Tests\Feature\Components;

use Tests\TestCase;

class CardComponentTest extends TestCase
{
    public function test_default_card_is_a_bordered_base_100_island(): void
    {
        $this->blade('<x-card title="Title">Body</x-card>')
            ->assertSee('bg-base-100', false)
            ->assertSee('border border-base-300', false)
            ->assertDontSee('card-inset', false);
    }

    public function test_inset_card_recesses_instead_of_floating(): void
    {
        $this->blade('<x-card title="Title" inset>Body</x-card>')
            ->assertSee('card-inset', false)
            ->assertDontSee('bg-base-100', false);
    }

    public function test_an_accent_colour_adds_a_top_edge_and_accepts_filament_names(): void
    {
        $this->blade('<x-card>Body</x-card>')->assertDontSee('border-t-4', false);
        $this->blade('<x-card accent="warning">Body</x-card>')->assertSee('border-t-4 border-t-warning', false);
        $this->blade('<x-card accent="danger">Body</x-card>')->assertSee('border-t-4 border-t-error', false);
    }

    public function test_a_footer_renders_below_the_body_under_a_divider(): void
    {
        $this->blade('<x-card>Body<x-slot:footer class="justify-end">Actions</x-slot:footer></x-card>')
            ->assertSeeInOrder(['Body', 'border-t border-base-300', 'Actions'], false)
            ->assertSee('justify-end', false);
    }

    public function test_a_sticky_footer_stays_in_view_only_when_asked(): void
    {
        $this->blade('<x-card>Body<x-slot:footer>Actions</x-slot:footer></x-card>')->assertDontSee('sticky bottom-0', false);
        $this->blade('<x-card sticky-footer>Body<x-slot:footer>Actions</x-slot:footer></x-card>')->assertSee('sticky bottom-0', false);
    }
}
