<?php

declare(strict_types=1);

namespace Tests\Feature\Components;

use Tests\TestCase;

class TabsNavComponentTest extends TestCase
{
    public function test_tabs_scroll_sideways_with_a_swipe_hint_by_default(): void
    {
        $this->blade('<x-tabs-nav><a role="tab">Overview</a></x-tabs-nav>')
            ->assertSee('data-tab-scroll', false)
            ->assertSee('tabs--scrollable', false)
            ->assertSee('tab-swipe-hint', false)
            ->assertSee(__('admin.swipe_for_more_tabs'));
    }

    public function test_tabs_wrap_without_the_hint_when_scrolling_is_off(): void
    {
        $this->blade('<x-tabs-nav :scrollable="false"><a role="tab">Overview</a></x-tabs-nav>')
            ->assertSee('Overview')
            ->assertDontSee('data-tab-scroll', false)
            ->assertDontSee('tab-swipe-hint', false);
    }
}
