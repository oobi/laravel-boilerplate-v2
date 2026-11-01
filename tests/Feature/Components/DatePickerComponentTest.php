<?php

declare(strict_types=1);

namespace Tests\Feature\Components;

use Tests\TestCase;

class DatePickerComponentTest extends TestCase
{
    public function test_a_range_picker_with_presets_lists_them_above_the_calendar(): void
    {
        $this->blade('<x-date-picker model="when" label="Dates" :presets="[\'today\' => \'Today\', \'upcoming\' => \'Upcoming\']" />')
            ->assertSeeInOrder(['Today', 'Upcoming', __('admin.date_picker.choose_dates'), '<calendar-range'], false)
            ->assertDontSee('<calendar-date', false);
    }

    public function test_a_single_date_picker_without_presets_is_just_the_calendar(): void
    {
        $this->blade('<x-date-picker model="date" mode="single" min="2026-10-01" />')
            ->assertSee('<calendar-date', false)
            ->assertSee('min="2026-10-01"', false)
            ->assertDontSee('role="listbox"', false)
            ->assertDontSee(__('admin.date_picker.choose_date'))
            ->assertDontSee(__('admin.date_picker.clear'));
    }

    public function test_the_month_arrows_carry_names_for_screen_readers(): void
    {
        foreach (['<x-date-picker model="date" mode="single" />', '<x-date-picker inline model="date" mode="single" />'] as $picker) {
            $this->blade($picker)
                ->assertSeeInOrder(['slot="previous"', __('admin.date_picker.previous_month'), 'slot="next"', __('admin.date_picker.next_month')], false);
        }
    }

    public function test_an_inline_picker_is_the_month_itself_with_no_button(): void
    {
        $this->blade('<x-date-picker inline id="closures" model="date" mode="single" />')
            ->assertSee('id="closures"', false)
            ->assertSee('<calendar-date', false)
            ->assertDontSee('<button', false)
            ->assertDontSee('role="dialog"', false);
    }

    public function test_an_inline_picker_names_itself_in_its_month_event(): void
    {
        $this->blade('<x-date-picker inline id="closures" model="date" mode="single" />')
            ->assertSee("\$dispatch('date-picker-month', { id: 'closures', month:", false);
    }

    public function test_an_inline_picker_marks_the_given_days_and_weekdays(): void
    {
        $this->blade('<x-date-picker inline model="date" mode="single" :marked="[\'2026-12-25\']" :marked-weekdays="[7]" />')
            ->assertSee('2026-12-25', false)
            ->assertSee("markedWeekdays = new Set(JSON.parse('[7]'))", false);
    }

    public function test_an_inline_picker_is_a_group_named_by_its_label(): void
    {
        $this->blade('<x-date-picker inline model="date" mode="single" label="Opening days" />')
            ->assertSee('role="group" aria-label="Opening days"', false);
    }

    public function test_an_inline_picker_is_replaced_when_its_marked_days_change(): void
    {
        $key = fn (string $marked): string => str((string) $this->blade('<x-date-picker inline model="date" mode="single" :marked="'.$marked.'" />'))
            ->match('/wire:key="([^"]+)"/')
            ->toString();

        $this->assertSame($key("['2026-12-25']"), $key("['2026-12-25']"));
        $this->assertNotSame($key("['2026-12-25']"), $key("['2026-12-26']"));
    }

    public function test_a_heading_picker_is_named_after_its_date_then_its_hint(): void
    {
        $this->blade('<x-date-picker display="heading" model="date" mode="single" hint="jump to a date" />')
            ->assertSee('ui-date-heading', false)
            ->assertSee('x-bind:aria-label="label + \', jump to a date\'"', false)
            ->assertDontSee('select flex', false);
    }

    public function test_a_heading_picker_without_a_hint_is_named_by_its_date_alone(): void
    {
        $this->blade('<x-date-picker display="heading" model="date" mode="single" />')
            ->assertSee('ui-date-heading', false)
            ->assertDontSee('x-bind:aria-label', false);
    }

    public function test_a_clearable_picker_offers_clear(): void
    {
        $this->blade('<x-date-picker model="date" mode="single" clearable />')
            ->assertSee(__('admin.date_picker.clear'));
    }
}
