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
}
