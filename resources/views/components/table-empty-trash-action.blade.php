{{--
    <x-table-empty-trash-action> — destructive header action for a
    <x-table-header> `actions` slot, e.g. permanently emptying the trash.
    Opens the matching Filament Action's own confirmation modal (the action
    name must have a corresponding {name}Action() method on the component).
--}}
@props([
    'action' => 'emptyTrash',
])

<button
    type="button"
    wire:click="mountAction('{{ $action }}')"
    {{ $attributes->class(['btn btn-outline btn-error']) }}
>
    {{ $slot }}
</button>
