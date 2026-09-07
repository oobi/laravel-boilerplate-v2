{{--
    <x-confirm-modal> — a ready-made "are you sure?" modal over <x-modal>. Renders
    the icon + heading + description and a two-button footer in the house order
    (cancel left, affirmative right), picking the affirmative button from the
    variant so a danger confirm gets a danger button automatically.

    Required:
    - wire:model — Livewire boolean that drives open/closed.
    - confirm — Livewire method to run on confirm.

    Props:
    - variant: danger (default) | warning | info | success | neutral.
    - title / description, confirm-label / cancel-label (label overrides).
--}}
@props([
    'variant' => 'danger',
    'title' => null,
    'description' => null,
    'confirm' => null,
    'confirmLabel' => null,
    'cancelLabel' => null,
])

@php
    $affirmative = match ($variant) {
        'danger' => 'button.danger',
        'warning' => 'button.warning',
        default => 'button.action',
    };
@endphp

<x-modal :variant="$variant" :title="$title" :description="$description" {{ $attributes->only('wire:model') }}>
    <x-slot:footer>
        <x-button.cancel class="flex-1" x-on:click="open = false">{{ $cancelLabel }}</x-button.cancel>

        <x-dynamic-component
            :component="$affirmative"
            class="flex-1"
            wire:click="{{ $confirm }}"
            x-on:click="open = false"
        >
            {{ $confirmLabel ?? __('admin.confirm') }}
        </x-dynamic-component>
    </x-slot:footer>
</x-modal>
