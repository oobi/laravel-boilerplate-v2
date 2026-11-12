<?php

declare(strict_types=1);

namespace Tests\Feature\Components;

use DOMDocument;
use DOMXPath;
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

    public function test_a_searchable_list_has_a_named_filter_box_that_narrows_every_option_but_the_empty_choice(): void
    {
        $view = $this->blade('<x-listbox model="event" label="Event" placeholder="All events" :options="[\'a\' => \'Logged in\', \'b\' => \'Logged out\']" searchable />');
        $html = (string) $view;

        $view->assertSee('type="search"', false)
            ->assertSee('aria-label="'.e(__('admin.filter_options', ['label' => 'Event'])).'"', false)
            ->assertSee('aria-controls="event-options"', false)
            ->assertSee('role="status"', false)
            ->assertSee(__('admin.no_matching_options'));
        // Each real option is filtered; the empty choice always stays.
        $this->assertSame(2, substr_count($html, 'x-show="matches('));
        $this->assertStringContainsString('labels: ', $html);
        // Enter with nothing typed keeps the choice; the status speaks only when nothing matches.
        $this->assertStringContainsString("if (this.query.trim() === '') { this.close(true); return }", $html);
        $this->assertStringContainsString('x-text="open && ! hasMatches ?', $html);
        $this->assertStringContainsString('max-h-80', $html);
    }

    public function test_a_server_searched_list_asks_the_named_method_and_shows_the_chosen_label(): void
    {
        $view = $this->blade('<x-listbox model="person" label="Person" placeholder="All people" search="searchPeople" selected-label="Ada Lovelace (ada@example.test)" :options="[\'x\' => \'Ignored option\']" />');
        $html = (string) $view;

        $view->assertSee('type="search"', false)
            ->assertSee('aria-label="'.e(__('admin.filter_options', ['label' => 'Person'])).'"', false)
            ->assertSee('role="status"', false)
            ->assertSee('All people')
            ->assertSee('Ada Lovelace (ada@example.test)')
            ->assertDontSee('Ignored option')
            ->assertSee(__('admin.type_to_search'))
            ->assertSee(__('admin.searching'))
            ->assertSee(__('admin.search_failed'))
            ->assertSee(__('admin.no_matching_options'));
        $this->assertStringContainsString('x-for="option in results"', $html);
        // The empty choice and the results template: no option is written into the page.
        $this->assertSame(2, substr_count($html, 'role="option"'));
    }

    public function test_the_alpine_component_survives_being_an_html_attribute_in_every_mode(): void
    {
        $modes = [
            '<x-listbox model="colour" placeholder="Any colour" :options="[1 => \'Red\']" />',
            '<x-listbox model="event" placeholder="All events" :options="[\'a\' => \'Logged in\']" searchable />',
            '<x-listbox model="person" placeholder="All people" search="searchPeople" selected-label="Ada (ada@example.test)" />',
        ];

        foreach ($modes as $markup) {
            // A stray double quote (in a comment, say) would end the attribute early and break the list silently.
            $dom = new DOMDocument;
            @$dom->loadHTML((string) $this->blade($markup));
            $component = trim((new DOMXPath($dom))->query('//*[@x-data]')->item(0)->getAttribute('x-data'));

            $this->assertStringStartsWith('{', $component);
            $this->assertStringEndsWith('}', $component);
        }
    }

    public function test_a_plain_list_has_no_filter_box_and_keeps_its_height(): void
    {
        $view = $this->blade('<x-listbox model="colour" placeholder="Any colour" :options="[1 => \'Red\']" />');

        $view->assertDontSee('type="search"', false)
            ->assertDontSee('x-show="matches(', false)
            ->assertDontSee('role="status"', false)
            ->assertDontSee(__('admin.no_matching_options'))
            ->assertSee('max-h-72', false);
    }
}
