<?php

declare(strict_types=1);

namespace Tests\Feature\Components;

use Tests\TestCase;

class TabsNavComponentTest extends TestCase
{
    public function test_tabs_scroll_by_default_and_keep_their_links_and_content_visible_without_initialization(): void
    {
        $this->blade(<<<'BLADE'
            <x-tabs-nav>
                <x-tabs-item active href="/current">Current</x-tabs-item>
                <x-tabs-item href="/versions" :badge="4">Versions</x-tabs-item>
                <x-tabs-item href="/changes">Proposed changes</x-tabs-item>
                <x-slot:content>Current document</x-slot:content>
            </x-tabs-nav>
            BLADE)
            ->assertSee('tabs--scrollable', false)
            ->assertSee('data-tab-scroll', false)
            ->assertDontSee('tabs--grid', false)
            ->assertDontSee('tab-swipe-hint', false)
            ->assertDontSee('sectionsTrigger', false)
            ->assertDontSee('x-cloak', false)
            ->assertDontSee('x-data', false)
            ->assertSeeInOrder(['href="/current"', 'aria-current="page"', 'Current', 'href="/versions"', 'Versions', 'badge', '4', 'href="/changes"', 'Proposed changes', 'Current document'], false);
    }

    public function test_scroll_controls_are_named_buttons_after_the_tab_viewport_hidden_until_the_tabs_overflow(): void
    {
        $this->blade('<x-tabs-nav :scrollable="true"><x-tabs-item href="/overview">Overview</x-tabs-item></x-tabs-nav>')
            ->assertSee('data-tab-scroll', false)
            ->assertSee('tabs--scrollable', false)
            ->assertSee('class="tab-scroll-controls" style="display: none;"', false)
            ->assertSeeInOrder(['Overview', '</div>', '</div>', 'tab-scroll-controls', 'type="button"', 'aria-label="Scroll tabs left"', 'type="button"', 'aria-label="Scroll tabs right"'], false)
            ->assertDontSee('tab-swipe-hint', false);
    }

    public function test_explicitly_disabling_scrolling_leaves_plain_tabs_without_controls(): void
    {
        $this->blade('<x-tabs-nav :scrollable="false"><x-tabs-item href="/overview">Overview</x-tabs-item></x-tabs-nav>')
            ->assertSee('Overview')
            ->assertDontSee('tabs--scrollable', false)
            ->assertDontSee('data-tab-scroll', false)
            ->assertDontSee('tab-scroll-controls', false)
            ->assertDontSee('tabs--grid', false)
            ->assertDontSee('sectionsTrigger', false);
    }

    public function test_six_tabs_keep_the_same_scroll_layout_and_every_destination_with_flush_content(): void
    {
        $this->blade(<<<'BLADE'
            <x-tabs-nav flush>
                <x-tabs-item href="/overview">Overview</x-tabs-item>
                <x-tabs-item href="/members">Members</x-tabs-item>
                <x-tabs-item href="/invitations">Invitations</x-tabs-item>
                <x-tabs-item href="/settings">Settings</x-tabs-item>
                <x-tabs-item href="/activity">Activity</x-tabs-item>
                <x-tabs-item active href="/billing">Billing</x-tabs-item>
                <x-slot:content>Billing details</x-slot:content>
            </x-tabs-nav>
            BLADE)
            ->assertSee('tabs--scrollable', false)
            ->assertSee('data-tab-scroll', false)
            ->assertSeeInOrder(['href="/overview"', 'href="/members"', 'href="/invitations"', 'href="/settings"', 'href="/activity"', 'href="/billing"', 'aria-current="page"', 'Billing details'], false)
            ->assertSee('ui-flush-content', false)
            ->assertDontSee('sectionsTrigger', false);
    }
}
