<?php

declare(strict_types=1);

namespace Tests\Feature\Components;

use Tests\TestCase;

class AvatarComponentTest extends TestCase
{
    public function test_an_icon_replaces_the_initials_and_keeps_the_colour_and_shape(): void
    {
        $this->blade('<x-avatar icon="heroicon-o-users" color="warning" square />')
            ->assertSee('avatar avatar-square', false)
            ->assertSee('avatar-warning avatar-soft', false)
            ->assertSee('avatar-icon', false)
            ->assertSee('aria-hidden="true"', false)
            ->assertDontSee('title=', false);
    }

    public function test_without_an_icon_it_shows_initials(): void
    {
        $this->blade('<x-avatar name="Jane Doe" />')
            ->assertSee('JD')
            ->assertSee('title="Jane Doe"', false)
            ->assertDontSee('avatar-icon', false);
    }
}
