<?php

declare(strict_types=1);

namespace Tests\Feature\Components;

use Tests\TestCase;

class FormActionsComponentTest extends TestCase
{
    public function test_the_actions_sit_in_a_sticky_row_that_knows_when_it_is_stuck(): void
    {
        $this->blade('<x-form-actions class="md:col-span-2"><button>Save</button><a>Cancel</a></x-form-actions>')
            ->assertSee('ui-form-actions md:col-span-2', false)
            ->assertSee('IntersectionObserver', false)
            ->assertSeeInOrder(['<button>Save</button>', '<a>Cancel</a>'], false)
            ->assertDontSee('ui-form-actions-secondary', false);
    }

    public function test_other_actions_on_the_record_sit_apart_behind_a_named_more_actions_button(): void
    {
        $this->blade('<x-form-actions><button>Save</button><x-slot:secondary><button>Delete</button></x-slot:secondary></x-form-actions>')
            ->assertSeeInOrder(['<button>Save</button>', 'ui-form-actions-secondary', 'aria-label="More actions"', 'ui-form-actions-more', '<button>Delete</button>'], false)
            ->assertSee('aria-expanded="false"', false);
    }
}
