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

    public function test_secondary_button_is_a_solid_secondary_colour_distinct_from_cancel(): void
    {
        $this->blade('<x-button.secondary>Show codes</x-button.secondary>')
            ->assertSee('btn-secondary', false)
            ->assertDontSee('btn-outline', false)
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

    public function test_the_dialog_is_named_and_described_by_its_own_title_and_copy(): void
    {
        $this->blade('<x-modal wire:model="showDelete" title="Delete user?" description="This can\'t be undone." />')
            ->assertSee('aria-labelledby="modal-showdelete-title"', false)
            ->assertSee('<h2 id="modal-showdelete-title"', false)
            ->assertSee('aria-describedby="modal-showdelete-description"', false)
            ->assertSee('<p id="modal-showdelete-description"', false);

        $this->blade('<x-modal wire:model="open">Body</x-modal>')
            ->assertDontSee('aria-labelledby', false)
            ->assertDontSee('aria-describedby', false);
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

    /**
     * A Livewire re-render (a wrong password's error) must not close an open
     * dialog (GitHub #35). The closing happens in the browser's morph, which a
     * PHP test can't run, so this pins the attribute that prevents it.
     */
    public function test_the_dialog_is_left_alone_when_livewire_re_renders(): void
    {
        $this->blade('<x-modal wire:model="open">Body</x-modal>')
            ->assertSeeInOrder(['<dialog', 'wire:ignore.self', 'x-data='], false);
    }

    /** Opening announces itself so the app shell closes its menu drawer first (GitHub #32). */
    public function test_opening_announces_itself_to_the_app_shell(): void
    {
        $this->blade('<x-modal wire:model="open">Body</x-modal>')
            ->assertSeeInOrder(['ui-modal-opened', 'showModal()'], false);
    }
}
