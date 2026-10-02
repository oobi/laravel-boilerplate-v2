<?php

declare(strict_types=1);

namespace Tests\Feature\Components;

use Tests\TestCase;

class EmptyStateComponentTest extends TestCase
{
    public function test_a_dashed_panel_with_an_icon_title_text_and_actions(): void
    {
        $this->blade('<x-empty-state icon="heroicon-o-inbox" title="No projects yet">Add one to get started.<x-slot:actions><button>Add</button></x-slot:actions></x-empty-state>')
            ->assertSee('border-dashed', false)
            ->assertSee('avatar-icon', false)
            ->assertSeeInOrder(['No projects yet', 'Add one to get started.', '<button>Add</button>'], false);
    }

    public function test_just_the_text_when_that_is_all_there_is(): void
    {
        $this->blade('<x-empty-state>Nothing here yet.</x-empty-state>')
            ->assertSee('Nothing here yet.')
            ->assertDontSee('avatar', false);
    }
}
