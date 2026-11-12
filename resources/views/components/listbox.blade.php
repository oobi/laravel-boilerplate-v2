{{--
    <x-listbox> — a select whose options can carry a coloured dot, which a
    native <select> can't show (e.g. categories or services in their
    colours), or that can be searched when it holds many options. Use
    <x-table-filter-select> when a short list of plain text options will do.
    Livewire-bound, styled like the other floating-label fields.

    Props:
      - model        wire:model path (required), e.g. "tableFilters.service.value"
      - label        floating label above the button
      - options      value => label
      - dots         value => classes for that option's dot, e.g. 'bg-primary'
                     (an option without one has no dot)
      - placeholder  the empty choice's label, listed first; null for none
      - searchable   a filter box at the top of the list, for a long one: typing
                     narrows the options (the empty choice always stays), Down
                     moves into them and Enter picks the first match
      - search       searches on the server instead, for a list too long to send
                     to the page (people): the name of a #[Json] method on the
                     Livewire component, called with the typed text a moment after
                     typing stops, returning at most a page of
                     [['value' => ..., 'label' => ...], ...] in the order to show.
                     It is a public endpoint: authorize in it. `options` and
                     `dots` are not used; the empty choice still is.
      - selected-label  with `search`: the label of the current value, which the
                     list can't know until someone searches (e.g. a filter set
                     from the URL)

    The list is teleported to <body> and anchored to the button, so a parent
    that clips its overflow (a tabbed card, an empty table) can't cut it off;
    it flips and shifts to stay on screen. Opening it (click, Enter, Space or
    Down) moves focus to the search box or the chosen option; Up opens it on the
    last option. Arrow keys move through the list; Escape or clicking away
    closes it.
--}}
@props([
    'model',
    'label' => null,
    'options' => [],
    'dots' => [],
    'placeholder' => null,
    'id' => null,
    'searchable' => false,
    'search' => null,
    'selectedLabel' => null,
])

@php
    $id ??= \Illuminate\Support\Str::slug(str_replace(['.', '_'], '-', $model));
    $remote = filled($search);
    $searchable = $searchable || $remote;
    $options = $remote ? [] : $options;
    $choices = ($placeholder === null ? [] : ['' => $placeholder]) + $options;
@endphp

<div
    x-data="{
        value: $wire.$entangle(@js($model), true),
        open: false,
        query: '',
        labels: @js(array_map('strval', array_values($options))),
        @if ($remote)
            // What the server found for the text typed, and where that search is.
            results: [],
            searching: false,
            failed: false,
            chosenLabel: @js((string) $selectedLabel),
            // The match count in words, for each count: a language may have more than two plural forms.
            counts: @js(collect(range(1, 100))->mapWithKeys(fn (int $count): array => [$count => trans_choice('admin.matches_found', $count)])),
            pending: null,
            asked: 0,
            find() {
                // Every change of text supersedes any search still waiting or on its way.
                this.cancel();
                const mine = this.asked;
                const text = this.query.trim();
                if (text === '') { this.results = []; this.failed = false; return }
                this.searching = true;
                this.pending = setTimeout(async () => {
                    let found = null;
                    try { found = await this.$wire.call(@js($search), text) } catch (error) { found = null }
                    if (mine !== this.asked) { return }
                    this.failed = ! Array.isArray(found);
                    this.results = Array.isArray(found) ? found.map((option) => ({ value: String(option.value), label: String(option.label) })) : [];
                    this.searching = false;
                }, 250);
            },
            cancel() { clearTimeout(this.pending); this.asked++; this.searching = false },
            // The pick re-renders the page, which drops the drawn results: start the next opening empty.
            pick(option) { this.value = option.value; this.chosenLabel = option.label; this.query = ''; this.results = []; this.close(true) },
        @endif
        openList(last = false) { this.query = ''; this.open = true; this.focusWhenShown(last) },
        toggle() { this.open ? this.close() : this.openList() },
        // Opening moves focus into the list: the search box, else the chosen option (or the last, for Up).
        // The list is hidden for a moment after it opens, while it's first anchored, and a hidden element can't take focus: wait until it shows (about two seconds at most).
        focusWhenShown(last = false, frames = 120) {
            if (! this.open) { return }
            if (! this.$refs.panel.checkVisibility()) { if (frames > 0) { requestAnimationFrame(() => this.focusWhenShown(last, frames - 1)) } return }
            @if ($searchable)
                this.$refs.search.focus();
            @else
                const options = [...this.$refs.panel.querySelectorAll('[data-option]')];
                (last ? options.at(-1) : (options.find((option) => option.getAttribute('aria-selected') === 'true') ?? options[0]))?.focus();
            @endif
        },
        close(refocus = false) { this.open = false; this.cancel?.(); if (refocus) { this.$refs.button.focus() } },
        isInside(target) { return this.$root.contains(target) || this.$refs.panel?.contains(target) },
        matches(text) { return text.toLocaleLowerCase().includes(this.query.trim().toLocaleLowerCase()) },
        @if ($remote)
            get hasMatches() { return this.results.length > 0 },
            // What the status says while the list is open: the server's answer has no other voice.
            get status() {
                if (! this.open || this.query.trim() === '') { return '' }
                if (this.searching) { return @js(__('admin.searching')) }
                if (this.failed) { return @js(__('admin.search_failed')) }
                if (! this.hasMatches) { return @js(__('admin.no_matching_options')) }
                return this.counts[this.results.length] ?? @js(trans_choice('admin.matches_found', 101, ['count' => ':count'])).replace(':count', this.results.length);
            },
        @else
            get hasMatches() { return this.labels.some((label) => this.matches(label)) },
        @endif
        // Enter with nothing typed keeps the current choice rather than taking the first option.
        pickFirstMatch() {
            if (this.query.trim() === '') { this.close(true); return }
            @if ($remote)
                // The results shown are for the text before the latest keystroke until the new ones arrive.
                if (this.searching) { return }
            @endif
            [...this.$refs.panel.querySelectorAll('[data-option]:not([data-empty-choice])')].find((option) => @js($remote) || this.matches(option.textContent))?.click();
        },
    }"
    @if ($remote)
        x-init="$watch('query', () => find())"
    @endif
    x-on:click.window="open && ! isInside($event.target) && close()"
    x-on:focusin.window="open && ! isInside($event.target) && close()"
    x-on:keydown.escape.window="open && (close(true), $event.preventDefault())"
    class="ui-floating-label"
    data-table-filter="{{ $model }}"
>
    <button
        x-ref="button"
        id="{{ $id }}"
        type="button"
        x-on:click="toggle()"
        x-on:keydown.down.prevent="open ? focusWhenShown() : openList()"
        x-on:keydown.up.prevent="open ? focusWhenShown(true) : openList(true)"
        @if ($searchable)
            {{-- Typing on the button opens the list and starts the search, so no letter is lost while it opens. --}}
            x-on:keydown="if ($event.key.length === 1 && ! $event.ctrlKey && ! $event.metaKey && ! $event.altKey && $event.key !== ' ') { $event.preventDefault(); open || openList(); query += $event.key }"
        @endif
        aria-haspopup="listbox"
        :aria-expanded="open"
        data-floating-label-up
        {{ $attributes->class(['select flex items-center gap-2 text-start']) }}
    >
        @foreach ($choices as $value => $choiceLabel)
            <span x-show="(value ?? '') == @js((string) $value)" @if ((string) $value !== '') x-cloak @endif class="flex min-w-0 items-center gap-2">
                @isset($dots[$value])
                    <span class="{{ $dots[$value] }} size-3 shrink-0 rounded-full" aria-hidden="true"></span>
                @endisset
                <span class="truncate">{{ $choiceLabel }}</span>
            </span>
        @endforeach

        @if ($remote)
            {{-- The chosen value isn't among any options the page holds: its label comes from the page or the pick. --}}
            <span x-show="(value ?? '') !== ''" x-cloak class="flex min-w-0 items-center gap-2">
                <span class="truncate" x-text="chosenLabel">{{ $selectedLabel }}</span>
            </span>
        @endif
    </button>
    @if ($label)
        <label for="{{ $id }}" class="ui-floating-label-text">{{ $label }}</label>
    @endif

    @if ($remote)
        {{-- Outside the list, which is hidden when closed, so a screen reader hears it. --}}
        <span class="sr-only" role="status" x-text="status"></span>
    @elseif ($searchable)
        {{-- Outside the list, which is hidden when closed, so a screen reader hears it. --}}
        <span class="sr-only" role="status" x-text="open && ! hasMatches ? @js(__('admin.no_matching_options')) : ''"></span>
    @endif

    <template x-teleport="body">
        <div
            x-ref="panel"
            x-show="open"
            x-cloak
            x-anchor.bottom-start.offset.4="$refs.button"
            :style="{ minWidth: $refs.button.offsetWidth + 'px' }"
            x-on:keydown.down.prevent="$focus.wrap().next()"
            x-on:keydown.up.prevent="$focus.wrap().previous()"
            @class([
                'z-50 flex w-max max-w-[calc(100vw-2rem)] flex-col rounded-box border border-base-300 bg-base-100 p-1 shadow-lg',
                'max-h-80' => $searchable,
                'max-h-72' => ! $searchable,
            ])
        >
            @if ($searchable)
                <input
                    x-ref="search"
                    x-model="query"
                    x-on:keydown.enter.prevent="pickFirstMatch()"
                    type="search"
                    autocomplete="off"
                    aria-label="{{ __('admin.filter_options', ['label' => $label ?? $placeholder]) }}"
                    aria-controls="{{ $id }}-options"
                    class="input input-sm mb-1 w-full shrink-0"
                />
            @endif

            <ul
                id="{{ $id }}-options"
                role="listbox"
                aria-labelledby="{{ $id }}"
                class="menu w-full flex-nowrap overflow-y-auto p-0"
            >
                @foreach ($choices as $value => $choiceLabel)
                    <li
                        wire:key="{{ $id }}-{{ $value }}"
                        @if (! $remote && $searchable && (string) $value !== '') x-show="matches(@js((string) $choiceLabel))" @endif
                    >
                        <button
                            type="button"
                            role="option"
                            data-option
                            @if ((string) $value === '') data-empty-choice @endif
                            :aria-selected="(value ?? '') == @js((string) $value)"
                            :class="{ 'menu-active': (value ?? '') == @js((string) $value) }"
                            x-on:click="value = @js((string) $value); close(true)"
                        >
                            @isset($dots[$value])
                                <span class="{{ $dots[$value] }} size-3 shrink-0 rounded-full" aria-hidden="true"></span>
                            @endisset
                            {{ $choiceLabel }}
                        </button>
                    </li>
                @endforeach

                @if ($remote)
                    <template x-for="option in results" :key="option.value">
                        <li>
                            <button
                                type="button"
                                role="option"
                                data-option
                                :aria-selected="String(value ?? '') === option.value"
                                :class="{ 'menu-active': String(value ?? '') === option.value }"
                                x-on:click="pick(option)"
                                x-text="option.label"
                            ></button>
                        </li>
                    </template>

                    <li x-show="query.trim() === ''" class="menu-disabled px-3 py-2 text-sm text-muted" role="presentation">
                        {{ __('admin.type_to_search') }}
                    </li>
                    <li x-show="query.trim() !== '' && searching && ! hasMatches" x-cloak class="menu-disabled px-3 py-2 text-sm text-muted" role="presentation">
                        {{ __('admin.searching') }}
                    </li>
                    <li x-show="query.trim() !== '' && ! searching && failed" x-cloak class="menu-disabled px-3 py-2 text-sm text-muted" role="presentation">
                        {{ __('admin.search_failed') }}
                    </li>
                    <li x-show="query.trim() !== '' && ! searching && ! failed && ! hasMatches" x-cloak class="menu-disabled px-3 py-2 text-sm text-muted" role="presentation">
                        {{ __('admin.no_matching_options') }}
                    </li>
                @elseif ($searchable)
                    <li x-show="! hasMatches" class="menu-disabled px-3 py-2 text-sm text-muted" role="presentation">
                        {{ __('admin.no_matching_options') }}
                    </li>
                @endif
            </ul>
        </div>
    </template>
</div>
