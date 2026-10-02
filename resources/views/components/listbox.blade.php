{{--
    <x-listbox> — a select whose options can carry a coloured dot, which a
    native <select> can't show (e.g. categories or services in their
    colours). Use <x-table-filter-select> when plain text options will do.
    Livewire-bound, styled like the other floating-label fields.

    Props:
      - model        wire:model path (required), e.g. "tableFilters.service.value"
      - label        floating label above the button
      - options      value => label
      - dots         value => classes for that option's dot, e.g. 'bg-primary'
                     (an option without one has no dot)
      - placeholder  the empty choice's label, listed first; null for none

    The list is teleported to <body> and anchored to the button, so a parent
    that clips its overflow (a tabbed card, an empty table) can't cut it off;
    it flips and shifts to stay on screen. Arrow keys move through the list;
    Escape or clicking away closes it.
--}}
@props([
    'model',
    'label' => null,
    'options' => [],
    'dots' => [],
    'placeholder' => null,
    'id' => null,
])

@php
    $id ??= \Illuminate\Support\Str::slug(str_replace(['.', '_'], '-', $model));
    $choices = ($placeholder === null ? [] : ['' => $placeholder]) + $options;
@endphp

<div
    x-data="{
        value: $wire.$entangle(@js($model), true),
        open: false,
        close(refocus = false) { this.open = false; if (refocus) { this.$refs.button.focus() } },
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
        x-on:click="open = ! open"
        x-on:keydown.down.prevent="open = true; $nextTick(() => $focus.within($refs.panel).first())"
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
    </button>
    @if ($label)
        <label for="{{ $id }}" class="ui-floating-label-text">{{ $label }}</label>
    @endif

    <template x-teleport="body">
        <ul
            x-ref="panel"
            x-show="open"
            x-cloak
            x-anchor.bottom-start.offset.4="$refs.button"
            :style="{ minWidth: $refs.button.offsetWidth + 'px' }"
            role="listbox"
            aria-labelledby="{{ $id }}"
            x-on:keydown.down.prevent="$focus.wrap().next()"
            x-on:keydown.up.prevent="$focus.wrap().previous()"
            class="menu z-50 max-h-72 w-max max-w-[calc(100vw-2rem)] flex-nowrap overflow-y-auto rounded-box border border-base-300 bg-base-100 p-1 shadow-lg"
        >
            @foreach ($choices as $value => $choiceLabel)
                <li wire:key="{{ $id }}-{{ $value }}">
                    <button
                        type="button"
                        role="option"
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
        </ul>
    </template>
</div>
