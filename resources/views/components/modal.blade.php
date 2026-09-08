{{--
    <x-modal> — the app's modal shell, built on the native <dialog> element so
    focus-trap, Esc-to-close, focus restore and an inert background come from the
    platform (accessible by default). Alpine only syncs the open state to a
    Livewire boolean via `wire:model`; visuals match the design system.

    Required:
    - wire:model — a Livewire boolean property that drives open/closed.

    Props:
    - variant: neutral (default) | danger | warning | info | success — sets the
      header icon + tint, and (via <x-confirm-modal>) the affirmative button.
    - title / description: heading + supporting copy (centered when an icon shows).
    - icon: heroicon name override (defaults per variant).
    - submit: a Livewire method name — when set, the body+footer become a
      <form wire:submit="…"> so a footer submit button posts the form.
    - closeable: bool (default true) — show the ✕ and allow Esc / backdrop close.

    Slots: default = body; `footer` = action buttons (rendered cancel-left,
    action-right; see docs/design-system.md). Buttons may call `open = false`
    (Alpine) to dismiss.
--}}
@props([
    'variant' => 'neutral',
    'title' => null,
    'description' => null,
    'icon' => null,
    'submit' => null,
    'closeable' => true,
])

@php
    $model = $attributes->wire('model')->value();

    // Full literal class strings (not interpolated) so Tailwind keeps them.
    $iconClasses = match ($variant) {
        'danger' => 'bg-soft-error text-error',
        'warning' => 'bg-soft-warning text-warning',
        'info' => 'bg-soft-info text-info',
        'success' => 'bg-soft-success text-success',
        default => null,
    };

    $resolvedIcon = $icon ?? match ($variant) {
        'danger', 'warning' => 'heroicon-o-exclamation-triangle',
        'info' => 'heroicon-o-information-circle',
        'success' => 'heroicon-o-check-circle',
        default => null,
    };

    // Destructive modals are alert dialogs (assertive) per WAI-ARIA.
    $role = $variant === 'danger' ? 'alertdialog' : 'dialog';
@endphp

<dialog
    wire:key="modal-{{ $model }}"
    x-data="{ open: $wire.entangle('{{ $model }}') }"
    x-init="$watch('open', value => value ? (! $el.open && $el.showModal()) : ($el.open && $el.close())); if (open) $el.showModal()"
    x-on:close="open = false"
    @unless ($closeable) x-on:cancel.prevent @endunless
    @if ($closeable) x-on:click="$event.target === $el && (open = false)" @endif
    role="{{ $role }}"
    {{ $attributes->except('wire:model')->class([
        'm-auto w-[calc(100%-2rem)] max-w-lg rounded-box bg-base-100 p-0 text-base-content shadow-2xl',
        '[&::backdrop]:bg-black/40 [&::backdrop]:backdrop-blur-sm',
    ]) }}
>
    <{{ $submit ? 'form' : 'div' }} @if ($submit) wire:submit="{{ $submit }}" @endif class="relative flex flex-col gap-4 p-6">
        @if ($closeable)
            <x-button.icon
                circle
                x-on:click="open = false"
                class="absolute end-2 top-2"
                aria-label="{{ __('admin.close') }}"
            >
                <x-dynamic-component component="heroicon-o-x-mark" class="h-5 w-5" />
            </x-button.icon>
        @endif

        @if ($resolvedIcon)
            <div class="mx-auto flex size-12 items-center justify-center rounded-full {{ $iconClasses }}">
                <x-dynamic-component :component="$resolvedIcon" class="size-6" />
            </div>
        @endif

        @if ($title)
            <h2 @class(['text-lg font-semibold', 'text-center' => $resolvedIcon])>{{ $title }}</h2>
        @endif

        @if ($description)
            <p @class(['ui-subtle text-sm', 'text-center' => $resolvedIcon])>{{ $description }}</p>
        @endif

        {{ $slot }}

        @isset($footer)
            <div class="mt-2 flex justify-end gap-3">
                {{ $footer }}
            </div>
        @endisset
    </{{ $submit ? 'form' : 'div' }}>
</dialog>
