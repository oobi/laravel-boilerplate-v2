{{--
    <x-dropdown>: an icon button (⋮ by default) that opens a short menu of
    actions, e.g. what you can do with one section of a note. A disclosure
    like the account menu: opens on click (not on focus), closes on Escape
    (focus back to the button), a click outside, Tabbing out, or choosing an
    item.

    Props:
    - label: the button's accessible name, since it has no text (e.g.
      'Actions for "Pricing"')
    - icon: a heroicon name (default heroicon-o-ellipsis-vertical)

    Slot: <x-dropdown.item> rows.
--}}
@props([
    'label',
    'icon' => 'heroicon-o-ellipsis-vertical',
])

<div
    {{ $attributes->class('dropdown dropdown-end') }}
    x-data="{ open: false }"
    x-id="['dropdown']"
    x-bind:class="{ 'dropdown-open': open }"
    x-on:keydown.escape="if (open) { $event.stopPropagation(); open = false; $refs.trigger.focus() }"
    x-on:click.outside="open = false"
    x-on:focusout="if (open && $event.relatedTarget && ! $el.contains($event.relatedTarget)) open = false"
>
    <x-button.icon
        x-ref="trigger"
        :aria-label="$label"
        x-bind:aria-controls="$id('dropdown')"
        x-bind:aria-expanded="open.toString()"
        aria-expanded="false"
        x-on:click="open = ! open"
    >
        <x-dynamic-component :component="$icon" class="h-5 w-5" aria-hidden="true" />
    </x-button.icon>

    <ul
        x-bind:id="$id('dropdown')"
        class="dropdown-content menu z-20 mt-1 w-56 rounded-box border border-base-300 bg-base-100 p-2 shadow"
        x-show="open"
        x-cloak
        x-on:click="if ($event.target.closest('button, a')) open = false"
    >
        {{ $slot }}
    </ul>
</div>
