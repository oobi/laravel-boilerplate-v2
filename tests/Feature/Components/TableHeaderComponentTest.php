<?php

declare(strict_types=1);

namespace Tests\Feature\Components;

use Tests\TestCase;

class TableHeaderComponentTest extends TestCase
{
    public function test_filters_fold_into_a_panel_the_filters_button_controls(): void
    {
        $view = $this->blade(<<<'BLADE'
            <x-table-header>
                <x-slot:search><x-table-search id="people-search" label="Search" /></x-slot:search>
                <x-slot:filters><x-table-filter-select model="tableFilters.status.value" label="Status" :options="['active' => 'Active']" /></x-slot:filters>
            </x-table-header>
            BLADE);

        $view->assertSeeInOrder(['ui-toolbar__filter-toggle', 'aria-expanded="false"', 'x-bind:aria-controls="$id(\'table-filters\')"', 'class="ui-toolbar__panel" x-bind:id="$id(\'table-filters\')"', 'ui-toolbar__filters'], false)
            ->assertSee('data-table-filter="tableFilters.status.value"', false);
    }

    public function test_a_toolbar_without_filters_or_counts_has_no_filters_button(): void
    {
        $this->blade(<<<'BLADE'
            <x-table-header>
                <x-slot:search><x-table-search id="invites-search" label="Search" /></x-slot:search>
                <x-slot:actions><x-button.action>Invite</x-button.action></x-slot:actions>
            </x-table-header>
            BLADE)
            ->assertDontSee('ui-toolbar__filter-toggle', false)
            ->assertDontSee('ui-toolbar__panel', false);
    }

    public function test_an_empty_trash_hides_its_toggle_and_leaves_no_panel(): void
    {
        $this->blade(<<<'BLADE'
            <x-table-header>
                <x-slot:search><x-table-search id="users-search" label="Search" /></x-slot:search>
                <x-slot:counts><x-table-trash-toggle :active-count="106" :trashed-count="0" /></x-slot:counts>
            </x-table-header>
            BLADE)
            ->assertDontSee('role="group"', false)
            ->assertDontSee('ui-toolbar__filter-toggle', false);
    }

    public function test_the_trash_view_keeps_its_toggle_and_counts_as_a_filter_once_emptied(): void
    {
        $this->blade('<x-table-trash-toggle value="0" :active-count="106" :trashed-count="0" />')
            ->assertSee('data-table-filter="tableFilters.trashed.value"', false)
            ->assertSee('aria-label="Active, 106 records"', false)
            ->assertDontSee("wire:click=\"\$set('tableFilters.trashed.value', '0')\"", false);
    }

    /**
     * Filament's header showing in every modal gave a table with our toolbar
     * two search boxes. CSS has no automated browser cover here: the test
     * guards the selector.
     */
    public function test_only_a_modal_table_without_our_toolbar_shows_filaments_header(): void
    {
        $css = file_get_contents(resource_path('css/theme/components/ui/filament-table.css'));

        $this->assertStringContainsString('.fi-modal .fi-ta:not(.ui-toolbar + .fi-ta) .fi-ta-header-ctn {', $css);
        $this->assertStringContainsString('.fi-modal .fi-ta:not(.ui-toolbar + .fi-ta) .fi-ta-header-toolbar > :not(.fi-ta-actions) {', $css);
        $this->assertStringNotContainsString("\n.fi-modal .fi-ta-header-ctn {", $css);
    }
}
