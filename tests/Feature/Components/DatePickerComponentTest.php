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
        $this->blade('<x-date-picker model="date" mode="single" />')
            ->assertSeeInOrder(['slot="previous"', __('admin.date_picker.previous_month'), 'slot="next"', __('admin.date_picker.next_month')], false);
    }

    public function test_a_clearable_picker_offers_clear(): void
    {
        $this->blade('<x-date-picker model="date" mode="single" clearable />')
            ->assertSee(__('admin.date_picker.clear'));
    }
}
