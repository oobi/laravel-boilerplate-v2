{{--
    <x-date-picker> — a dropdown date picker for toolbars and filters (a
    <x-table-header> `filters` slot, a page toolbar), or with `inline` or
    display="heading" a month always open or a date as a page's title (see
    the props), built on Cally's calendar
    (daisyUI's .cally styles it). Livewire-bound: `model` is the full
    wire:model path, e.g. "tableFilters.when.value" or "date".

    Value: "Y-m-d" (mode="single") or "Y-m-d/Y-m-d" (mode="range"; tap the
    first day, then the last, or one day twice), or a preset's key. Read a
    picked value with App\Support\DateRange::parse().

    Props:
      - model        wire:model path (required)
      - label        floating label above the button
      - mode         'range' (default) or 'single'
      - presets      key => label shortcuts listed above the calendar, e.g.
                     ['today' => 'Today', 'upcoming' => 'Upcoming']. Empty
                     (default): the button opens straight to the calendar.
      - pick-label   the menu item that opens the calendar when there are
                     presets (default "Choose dates…" / "Choose a date…")
      - placeholder  button text with no value (default "Any date")
      - clearable    a Clear button under the calendar once a day is picked
      - today, min, max, first-day-of-week, locale: passed to Cally ("Y-m-d"
                     dates; week starts Monday; locale defaults to the app's)
      - display      'field' (default): a select-style button. 'heading': the
                     day written out ("Sunday November 1") as a large button,
                     for a page whose title is the date (a day's schedule).
                     Put it inside the page's <h1>, so it is the heading.
      - hint         heading only: what the button does ("jump to a date"),
                     named after the date it shows ("Sunday November 1,
                     jump to a date") so the name starts with the visible text
                     (WCAG 2.5.3). Use it, not aria-label.
      - inline       the calendar itself, always open, with no button
                     (mode="single" only), e.g. a month in a sidebar. `label`
                     names it for screen readers; presets and clearable
                     don't apply.
      - marked       inline only: "Y-m-d" days to mark (shaded and struck
                     through), e.g. closures. Say what they are in text
                     beside the calendar: the mark is visual only.
      - marked-weekdays  inline only: ISO weekdays (1 = Monday) marked the
                     same way, e.g. the days a business doesn't open. Marked
                     days stay selectable.
      The inline month dispatches `date-picker-month` on the window whenever
      the month on screen changes, for text that follows it. Its detail is
      { id, month } (the picker's id and "Y-m"), so a listener can ignore
      other pickers on the page:
      x-on:date-picker-month.window="$event.detail.id === 'closures' && (month = $event.detail.month)"

    The button names the value: a preset's label, or the days formatted for
    the locale ("Thu 8 Oct", "8–14 Oct"), with the year when it isn't this
    year. The panel is teleported to <body> and anchored to the button, so a
    parent that clips its overflow (a tabbed card, an empty table) can't cut
    it off; it flips and shifts to stay on screen. Arrow keys move through
    the presets and the calendar; Escape or clicking away closes it.
--}}
@props([
    'model',
    'label' => null,
    'mode' => 'range',
    'presets' => [],
    'pickLabel' => null,
    'placeholder' => null,
    'clearable' => false,
    'today' => null,
    'min' => null,
    'max' => null,
    'firstDayOfWeek' => 1,
    'locale' => null,
    'id' => null,
    'display' => 'field',
    'hint' => null,
    'inline' => false,
    'marked' => [],
    'markedWeekdays' => [],
])

@php
    $id ??= \Illuminate\Support\Str::slug(str_replace(['.', '_'], '-', $model));
    $isRange = $mode === 'range';
    $pickLabel ??= __($isRange ? 'admin.date_picker.choose_dates' : 'admin.date_picker.choose_date');
    $placeholder ??= __('admin.date_picker.any_date');
    $locale ??= str_replace('_', '-', app()->getLocale());
    $calendar = $isRange ? 'calendar-range' : 'calendar-date';
    $isHeading = $display === 'heading';
@endphp

@if ($inline)
    {{-- Always open: just the month, kept out of Livewire's re-renders and synced from the value.
         The key changes with the marked days and limits, so new ones replace the month. --}}
    <div
        id="{{ $id }}"
        wire:key="{{ $id }}-{{ md5(json_encode([$marked, $markedWeekdays, $today, $min, $max])) }}"
        x-data="{ value: $wire.$entangle(@js($model), true) }"
        wire:ignore
        @if ($label) role="group" aria-label="{{ $label }}" @endif
        {{ $attributes->class(['ui-date-picker-inline']) }}
    >
        <calendar-date
            class="cally w-full"
            first-day-of-week="{{ $firstDayOfWeek }}"
            locale="{{ $locale }}"
            @if ($today) today="{{ $today }}" @endif
            @if ($min) min="{{ $min }}" @endif
            @if ($max) max="{{ $max }}" @endif
            {{-- x-init turns the page only when the month changes: moving the focused day also focuses
                 it, which would scroll a sidebar to the calendar on every load. (No comment inside the
                 attribute: Alpine runs it as statements only when it starts with one.) --}}
            x-init="
                const marked = new Set({{ \Illuminate\Support\Js::from(array_values($marked)) }});
                const markedWeekdays = new Set({{ \Illuminate\Support\Js::from(array_values($markedWeekdays)) }});
                $el.getDayParts = (day) => marked.has(day.toISOString().slice(0, 10)) || markedWeekdays.has(day.getUTCDay() || 7) ? 'marked' : '';
                const announce = () => $dispatch('date-picker-month', { id: @js($id), month: String($el.focusedDate ?? value ?? '').slice(0, 7) });
                const sync = () => {
                    $el.value = value ?? '';
                    if (value && value.slice(0, 7) !== String($el.focusedDate ?? '').slice(0, 7)) { $el.focusedDate = value; announce() }
                };
                sync();
                $watch('value', sync);
            "
            x-on:change="value = $event.target.value"
            x-on:focusday="$nextTick(() => $dispatch('date-picker-month', { id: @js($id), month: String($el.focusedDate ?? '').slice(0, 7) }))"
        >
            {{-- Cally names its previous/next buttons from this slotted text, so each arrow carries hidden text. --}}
            <span slot="previous"><x-heroicon-m-chevron-left class="size-4" /><span class="sr-only">{{ __('admin.date_picker.previous_month') }}</span></span>
            <span slot="next"><x-heroicon-m-chevron-right class="size-4" /><span class="sr-only">{{ __('admin.date_picker.next_month') }}</span></span>
            <calendar-month></calendar-month>
        </calendar-date>
    </div>
@else

<div
    x-data="{
        value: $wire.$entangle(@js($model), true),
        presets: @js((object) $presets),
        hasPresets: @js($presets !== []),
        open: false,
        choosing: false,
        get days() {
            const match = typeof this.value === 'string' && this.value.match(/^(\d{4}-\d{2}-\d{2})(?:\/(\d{4}-\d{2}-\d{2}))?$/);

            return match ? [match[1], match[2] ?? match[1]].sort() : null;
        },
        get isPicked() { return this.days !== null && ! (this.value in this.presets) },
        get label() {
            if (this.value in this.presets) { return this.presets[this.value] }
            if (! this.days) { return @js($placeholder) }

            const [from, to] = this.days.map((day) => new Date(day + 'T00:00:00Z'));
            const thisYear = Number((@js($today) ?? new Date().toISOString()).slice(0, 4));
            // A heading spells the day out, but not on a phone, where the header is short of room.
            const long = @js($isHeading) && window.matchMedia('(min-width: 640px)').matches;
            // On the smallest phones a heading drops the weekday (3 Oct) to fit beside the day stepper.
            const weekday = ! (@js($isHeading) && window.matchMedia('(max-width: 379.98px)').matches);
            const options = { day: 'numeric', month: long ? 'long' : 'short', timeZone: 'UTC' };

            if (from.getUTCFullYear() !== thisYear || to.getUTCFullYear() !== thisYear) { options.year = 'numeric' }

            // A heading drops the comma after the weekday (Sat 3 Oct): it only costs room.
            const day = (date) => {
                const text = new Intl.DateTimeFormat(@js($locale), { ...(weekday ? { weekday: long ? 'long' : 'short' } : {}), ...options }).format(date);

                return @js($isHeading) ? text.replace(',', '') : text;
            };

            return +from === +to
                ? day(from)
                : new Intl.DateTimeFormat(@js($locale), options).formatRange(from, to);
        },
        toggle() {
            if (this.open) { return this.close() }
            this.open = true;
            if (! this.hasPresets) { this.choose() }
        },
        close(refocus = false) { this.open = false; this.choosing = false; if (refocus) { this.$refs.button.focus() } },
        pick(value) { this.value = value; this.close(true) },
        choose() { this.choosing = true; this.$nextTick(() => this.$refs.calendar.focus()) },
        isInside(target) { return this.$root.contains(target) || this.$refs.panel?.contains(target) },
    }"
    x-on:click.window="open && ! isInside($event.target) && close()"
    x-on:focusin.window="open && ! isInside($event.target) && close()"
    x-on:keydown.escape.window="open && (close(true), $event.preventDefault())"
    @class(['ui-floating-label', 'min-w-0' => $isHeading])
    data-table-filter="{{ $model }}"
>
    <button
        x-ref="button"
        id="{{ $id }}"
        type="button"
        x-on:click="toggle()"
        x-on:keydown.down.prevent="toggle(); hasPresets && $nextTick(() => $focus.within($refs.list).first())"
        aria-haspopup="dialog"
        :aria-expanded="open"
        data-floating-label-up
        @if ($isHeading)
            @if ($hint)
                x-bind:aria-label="label + @js(', '.$hint)"
            @endif
            {{ $attributes->class(['ui-date-heading']) }}
        >
            <span class="truncate" x-text="label"></span>
            <x-heroicon-m-chevron-down class="hidden size-4 shrink-0 opacity-50 sm:block" />
        @else
            {{ $attributes->class(['select flex items-center gap-2 text-start']) }}
        >
            <x-heroicon-o-calendar-days class="size-4 shrink-0 opacity-60" />
            <span class="truncate" x-text="label"></span>
        @endif
    </button>
    @if ($label)
        <label for="{{ $id }}" class="ui-floating-label-text">{{ $label }}</label>
    @endif

    <template x-teleport="body">
        <div
            x-ref="panel"
            x-show="open"
            x-cloak
            x-anchor.bottom-start.offset.4="$refs.button"
            role="dialog"
            aria-labelledby="{{ $id }}"
            class="z-50 w-72 max-w-[calc(100vw-2rem)] rounded-box border border-base-300 bg-base-100 p-1 shadow-lg"
        >
            @if ($presets !== [])
                <ul
                    x-ref="list"
                    role="listbox"
                    aria-labelledby="{{ $id }}"
                    x-on:keydown.down.prevent="$focus.wrap().next()"
                    x-on:keydown.up.prevent="$focus.wrap().previous()"
                    class="menu w-full"
                >
                    @foreach ($presets as $key => $presetLabel)
                        <li wire:key="{{ $id }}-{{ $key }}">
                            <button
                                type="button"
                                role="option"
                                :aria-selected="value === @js((string) $key)"
                                :class="{ 'menu-active': value === @js((string) $key) }"
                                x-on:click="pick(@js((string) $key))"
                            >
                                {{ $presetLabel }}
                            </button>
                        </li>
                    @endforeach
                    <li>
                        <button
                            type="button"
                            role="option"
                            :aria-selected="isPicked"
                            :class="{ 'menu-active': isPicked }"
                            x-on:click="choose()"
                        >
                            {{ $pickLabel }}
                        </button>
                    </li>
                </ul>
            @endif

            {{-- Kept out of Livewire's re-renders so a half-picked range survives them. --}}
            <div
                x-show="choosing || isPicked || ! hasPresets"
                wire:ignore
                @class(['border-t border-base-300 pt-1' => $presets !== []])
            >
                <{{ $calendar }}
                    x-ref="calendar"
                    class="cally w-full"
                    first-day-of-week="{{ $firstDayOfWeek }}"
                    locale="{{ $locale }}"
                    @if ($today) today="{{ $today }}" @endif
                    @if ($min) min="{{ $min }}" @endif
                    @if ($max) max="{{ $max }}" @endif
                    x-init="
                        const sync = () => {
                            $el.value = isPicked ? (@js($isRange) ? days.join('/') : days[0]) : '';
                            if (isPicked) { $el.focusedDate = days[0] }
                        };
                        sync();
                        $watch('value', sync);
                    "
                    x-on:change="pick($event.target.value)"
                >
                    {{-- Cally names its previous/next buttons from this slotted text, so each arrow carries hidden text. --}}
                    <span slot="previous"><x-heroicon-m-chevron-left class="size-4" /><span class="sr-only">{{ __('admin.date_picker.previous_month') }}</span></span>
                    <span slot="next"><x-heroicon-m-chevron-right class="size-4" /><span class="sr-only">{{ __('admin.date_picker.next_month') }}</span></span>
                    <calendar-month></calendar-month>
                </{{ $calendar }}>

                @if ($clearable)
                    <div x-show="isPicked" class="flex justify-end px-2 pb-2">
                        <button type="button" class="btn btn-ghost btn-sm" x-on:click="pick('')">
                            {{ __('admin.date_picker.clear') }}
                        </button>
                    </div>
                @endif
            </div>
        </div>
    </template>
</div>
@endif
