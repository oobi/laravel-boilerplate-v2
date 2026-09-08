<?php

declare(strict_types=1);

namespace Tests\Feature\Components;

use Tests\TestCase;

class ModalComponentsTest extends TestCase
{
    public function test_semantic_buttons_render_their_intent_colour(): void
    {
        $this->blade('<x-button.action>Save</x-button.action>')->assertSee('btn-primary', false)->assertSee('Save');
        $this->blade('<x-button.danger>Delete</x-button.danger>')->assertSee('btn-error', false);
        $this->blade('<x-button.warning>Impersonate</x-button.warning>')->assertSee('btn-warning', false);
    }

    public function test_cancel_button_defaults_its_label(): void
    {
        $this->blade('<x-button.cancel />')
            ->assertSee('btn-neutral', false)
            ->assertSee('btn-outline', false)
            ->assertSee(__('admin.cancel'));
    }

    public function test_back_button_is_a_neutral_ghost_link_defaulting_its_label(): void
    {
        $this->blade('<x-button.back href="/users" />')
            ->assertSee('btn-neutral', false)
            ->assertSee('btn-ghost', false)
            ->assertSee('<a href="/users"', false)
            ->assertSee(__('admin.back'));
    }

    public function test_secondary_button_is_a_neutral_outline(): void
    {
        $this->blade('<x-button.secondary>Show codes</x-button.secondary>')
            ->assertSee('btn-neutral', false)
            ->assertSee('btn-outline', false)
            ->assertSee('Show codes');
    }

    public function test_icon_button_is_a_ghost_square_by_default_and_circle_on_request(): void
    {
        $this->blade('<x-button.icon aria-label="Close">x</x-button.icon>')
            ->assertSee('btn-ghost', false)
            ->assertSee('btn-square', false)
            ->assertSee('aria-label="Close"', false);

        $this->blade('<x-button.icon circle aria-label="Close">x</x-button.icon>')
            ->assertSee('btn-circle', false)
            ->assertDontSee('btn-square', false);
    }

    public function test_modal_renders_a_native_dialog_with_its_title(): void
    {
        $this->blade('<x-modal wire:model="open" :title="$title" />', ['title' => 'Are you sure?'])
            ->assertSee('<dialog', false)
            ->assertSee('role="dialog"', false)
            ->assertSee('Are you sure?');
    }

    public function test_danger_confirm_modal_is_an_alertdialog_with_a_danger_button(): void
    {
        $this->blade(
            '<x-confirm-modal wire:model="open" variant="danger" :title="$title" confirm="delete" :confirm-label="$label" />',
            ['title' => 'Delete this record?', 'label' => 'Yes, delete it'],
        )
            ->assertSee('role="alertdialog"', false)
            ->assertSee('btn-error', false)
            ->assertSee('Yes, delete it')
            ->assertSee(__('admin.cancel'));
    }

    public function test_warning_confirm_modal_uses_a_warning_button(): void
    {
        $this->blade(
            '<x-confirm-modal wire:model="open" variant="warning" :title="$title" confirm="go" />',
            ['title' => 'Impersonate?'],
        )
            ->assertSee('btn-warning', false)
            ->assertSee('role="dialog"', false);
    }
}
