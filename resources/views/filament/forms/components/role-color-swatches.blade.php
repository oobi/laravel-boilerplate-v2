@php
    use App\Support\Theme\DaisyColor;

    $statePath = $getStatePath();
    // The sibling "name" field, so every badge previews *this role's* badge (and all read the same width).
    $namePath = $field->getContainer()->getStatePath().'.name';
    $placeholder = __('admin.role_badge_placeholder');
    $initialName = $getRecord()?->name ?: $placeholder;
    $colorLabels = collect(DaisyColor::cases())->mapWithKeys(fn (DaisyColor $color): array => [$color->value => $color->getLabel()]);
@endphp

{{--
    Badge colour picker: the badge this role will actually get (its name in the
    chosen colour) *is* the control — click it to open a panel of the same badge
    in all eight colours and pick one. No select chrome. Colour names never
    appear on screen — the badge is the information — but each option carries
    one as its accessible name, since eight identical words are useless to a
    screen reader. Alpine owns open/close (outside click, Escape), the entangled
    colour state, and the live role-name preview.
--}}
<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    <div
        x-data="{
            state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$statePath}')") }},
            open: false,
            labels: @js($colorLabels),
            preview() { return $wire.get(@js($namePath)) || @js($placeholder) },
        }"
        x-on:click.outside="open = false"
        x-on:keydown.escape.prevent="open = false"
        class="dropdown flex h-10 items-center"
        :class="{ 'dropdown-open': open }"
    >
        <button
            type="button"
            x-on:click="open = ! open"
            class="badge badge-soft cursor-pointer gap-1 pr-1.5 text-xs transition hover:brightness-95 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
            :class="'badge-' + state"
            aria-haspopup="listbox"
            :aria-expanded="open"
            :aria-label="'{{ __('admin.badge_color') }}: ' + labels[state]"
            id="{{ $getId() }}"
        >
            <span x-text="preview()">{{ $initialName }}</span>
            <x-heroicon-m-chevron-down class="h-3 w-3 opacity-70" />
        </button>

        <div
            x-show="open"
            x-cloak
            role="listbox"
            aria-labelledby="{{ $getId() }}"
            class="dropdown-content z-30 mt-1 flex w-max max-w-xs flex-wrap gap-2 rounded-box border border-base-300 bg-base-100 p-3 shadow"
        >
            @foreach (DaisyColor::cases() as $swatch)
                <button
                    type="button"
                    role="option"
                    aria-label="{{ $swatch->getLabel() }}"
                    wire:key="role-color-swatch-{{ $swatch->value }}"
                    x-on:click="state = '{{ $swatch->value }}'; open = false"
                    :aria-selected="state === '{{ $swatch->value }}'"
                    class="badge badge-soft badge-{{ $swatch->value }} cursor-pointer text-xs transition hover:brightness-95 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
                    :class="{ 'ring-2 ring-base-content/40 ring-offset-2 ring-offset-base-100': state === '{{ $swatch->value }}' }"
                    x-text="preview()"
                >{{ $initialName }}</button>
            @endforeach
        </div>
    </div>
</x-dynamic-component>
