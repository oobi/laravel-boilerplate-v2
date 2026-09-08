{{--
    <x-table-empty-trash-action> — destructive header action for a
    <x-table-header> `actions` slot, e.g. permanently emptying the trash.
    Opens the matching Filament Action's own confirmation modal (the action
    name must have a corresponding {name}Action() method on the component).
--}}
@props([
    'action' => 'emptyTrash',
])

<x-button.danger
    type="button"
    variant="outline"
    wire:click="mountAction('{{ $action }}')"
    {{ $attributes }}
>
    {{ $slot }}
</x-button.danger>
