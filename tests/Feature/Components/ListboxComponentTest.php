<?php

declare(strict_types=1);

namespace Tests\Feature\Components;

use Tests\TestCase;

class ListboxComponentTest extends TestCase
{
    public function test_options_carry_their_dots_after_the_empty_choice(): void
    {
        $this->blade('<x-listbox model="colour" placeholder="Any colour" :options="[1 => \'Red\', 2 => \'Plain\']" :dots="[1 => \'bg-error\']" />')
            ->assertSeeInOrder(['Any colour', 'bg-error', 'Red', 'Plain'], false)
            ->assertSee('class="bg-error size-3', false);
    }

    public function test_without_a_placeholder_there_is_no_empty_choice(): void
    {
        $view = $this->blade('<x-listbox model="colour" :options="[1 => \'Red\']" />');

        $this->assertSame(1, substr_count((string) $view, 'role="option"'));
        $view->assertDontSee('size-3 shrink-0 rounded-full', false);
    }
}
