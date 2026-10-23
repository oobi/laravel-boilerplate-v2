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

    public function test_another_action_on_the_record_is_kept_apart_after_the_pair(): void
    {
        $this->blade('<x-form-actions><button>Save</button><x-slot:secondary><button>Delete</button></x-slot:secondary></x-form-actions>')
            ->assertSeeInOrder(['<button>Save</button>', 'ui-form-actions-secondary', '<button>Delete</button>'], false);
    }
}
