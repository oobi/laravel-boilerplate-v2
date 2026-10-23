<?php

declare(strict_types=1);

namespace Tests\Feature\Components;

use Tests\TestCase;

class DropdownComponentTest extends TestCase
{
    public function test_a_named_closed_disclosure_button_over_its_items(): void
    {
        $this->blade('<x-dropdown label="Actions for Pricing"><x-dropdown.item wire:click="edit(1)">Propose change</x-dropdown.item></x-dropdown>')
            ->assertSee('aria-label="Actions for Pricing"', false)
            ->assertSee('aria-expanded="false"', false)
            ->assertSeeInOrder(['x-ref="trigger"', '<li>', 'wire:click="edit(1)"', 'Propose change'], false);
    }

    public function test_an_item_takes_its_intent_colour(): void
    {
        $this->blade('<x-dropdown label="Actions"><x-dropdown.item color="warning">Retire</x-dropdown.item><x-dropdown.item>Change</x-dropdown.item></x-dropdown>')
            ->assertSeeInOrder(['text-warning', 'Retire', 'Change'], false)
            ->assertSee('text-warning', false)
            ->assertDontSee('text-error-text', false);
    }
}
