@php
    $statePath = $getStatePath();
@endphp

<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    <div
        x-data="{ state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$statePath}')") }} }"
        class="flex flex-wrap gap-2"
    >
        @foreach (\App\Support\Theme\DaisyColor::cases() as $swatch)
            <button
                type="button"
                x-on:click="state = '{{ $swatch->value }}'"
                :class="[
                    'btn btn-xs btn-square btn-{{ $swatch->value }}',
                    state !== '{{ $swatch->value }}' ? 'btn-soft' : '',
                ]"
                aria-label="{{ $swatch->getLabel() }}"
                wire:key="role-color-swatch-{{ $swatch->value }}"
            ></button>
        @endforeach
    </div>
</x-dynamic-component>
