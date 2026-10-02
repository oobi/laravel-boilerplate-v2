{{--
    <x-date-picker> — a dropdown date picker for toolbars and filters (a
    <x-table-header> `filters` slot, a page toolbar), built on Cally's calendar
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
])

@php
    $id ??= \Illuminate\Support\Str::slug(str_replace(['.', '_'], '-', $model));
    $isRange = $mode === 'range';
    $pickLabel ??= __($isRange ? 'admin.date_picker.choose_dates' : 'admin.date_picker.choose_date');
    $placeholder ??= __('admin.date_picker.any_date');
    $locale ??= str_replace('_', '-', app()->getLocale());
    $calendar = $isRange ? 'calendar-range' : 'calendar-date';
@endphp

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
            const options = { day: 'numeric', month: 'short', timeZone: 'UTC' };

            if (from.getUTCFullYear() !== thisYear || to.getUTCFullYear() !== thisYear) { options.year = 'numeric' }

            return +from === +to
                ? new Intl.DateTimeFormat(@js($locale), { weekday: 'short', ...options }).format(from)
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
    class="ui-floating-label"
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
        {{ $attributes->class(['select flex items-center gap-2 text-start']) }}
    >
        <x-heroicon-o-calendar-days class="size-4 shrink-0 opacity-60" />
        <span class="truncate" x-text="label"></span>
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
                    {{-- Cally labels its own previous/next buttons; these are just the arrows. --}}
                    <svg slot="previous" class="size-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M11.78 5.22a.75.75 0 0 1 0 1.06L8.06 10l3.72 3.72a.75.75 0 1 1-1.06 1.06l-4.25-4.25a.75.75 0 0 1 0-1.06l4.25-4.25a.75.75 0 0 1 1.06 0Z" clip-rule="evenodd" /></svg>
                    <svg slot="next" class="size-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M8.22 5.22a.75.75 0 0 1 1.06 0l4.25 4.25a.75.75 0 0 1 0 1.06l-4.25 4.25a.75.75 0 0 1-1.06-1.06L11.94 10 8.22 6.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" /></svg>
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
