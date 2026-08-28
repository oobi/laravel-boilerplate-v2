{{--
    <x-table-empty-trash-action> — destructive header action for a
    <x-table-header> `actions` slot, e.g. permanently emptying the trash.
--}}
@props([
    'action' => 'emptyTrash',
    'confirm' => null,
])

<button
    type="button"
    wire:click="{{ $action }}"
    @if ($confirm) wire:confirm="{{ $confirm }}" @endif
    {{ $attributes->class(['btn btn-outline btn-error']) }}
>
    {{ $slot }}
</button>
