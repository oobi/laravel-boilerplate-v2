<?php

declare(strict_types=1);

namespace Tests\Feature;

use Filament\Actions\Action;
use Filament\Support\Enums\Alignment;
use Tests\TestCase;

/**
 * The global Filament modal defaults registered in AppServiceProvider keep
 * Filament-rendered modals aligned with the app's own <x-modal> conventions:
 * an outlined cancel button and a cancel-left / action-right footer order.
 */
class FilamentModalDefaultsTest extends TestCase
{
    public function test_modal_cancel_action_is_outlined_to_match_the_cancel_button_component(): void
    {
        $this->assertTrue(Action::make('cancel')->isOutlined());
    }

    public function test_other_actions_are_not_forced_to_outline(): void
    {
        $this->assertFalse(Action::make('submit')->isOutlined());
    }

    public function test_form_modals_right_align_the_footer_so_cancel_sits_left_of_the_action(): void
    {
        // A non-confirmation (form) modal: End alignment pairs with Filament's
        // flex-row-reverse footer to render cancel-left / action-right.
        $this->assertSame(
            Alignment::End,
            Action::make('save')->getModalFooterActionsAlignment(),
        );
    }

    public function test_confirmation_modals_keep_filaments_centered_footer(): void
    {
        $this->assertSame(
            Alignment::Center,
            Action::make('delete')->requiresConfirmation()->getModalFooterActionsAlignment(),
        );
    }

    public function test_modals_stick_their_header_and_footer_so_long_content_scrolls_between_them(): void
    {
        $action = Action::make('edit');

        $this->assertTrue($action->isModalHeaderSticky());
        $this->assertTrue($action->isModalFooterSticky());
    }
}
