<?php

declare(strict_types=1);

namespace Tests\Feature\Components;

use Tests\TestCase;

class ButtonComponentTest extends TestCase
{
    public function test_surface_fills_an_outline_button_only_when_asked(): void
    {
        $this->blade('<x-button variant="outline">Go</x-button>')->assertDontSee('btn-surface', false);
        $this->blade('<x-button variant="outline" surface>Go</x-button>')->assertSee('btn btn-primary btn-outline btn-surface', false);
    }

    public function test_a_wrapped_button_keeps_a_query_string_link_intact(): void
    {
        $this->blade('<x-button.action :href="$url">Go</x-button.action>', ['url' => '/merge?a=1&b=2'])
            ->assertSee('href="/merge?a=1&amp;b=2"', false);
    }
}
