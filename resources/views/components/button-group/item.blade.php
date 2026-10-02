{{--
    <x-button-group.item>: one segment of an <x-button-group>. A <button>
    (aria-pressed says whether it's the chosen one), or a link with href
    (aria-current). Passes everything else through (wire:click, x-on:click,
    title, ...).

    Props:
    - active: whether this segment is chosen (default: false)
    - color: the active colour: primary, danger (or error), neutral (default: primary)
    - href: renders a link instead of a button
    - type: button, submit, reset (default: button)

    Active state driven by Alpine instead of the server: bind the same class and
    attribute yourself, e.g.
    x-bind:class="{ 'ui-button-group-btn-active-neutral': mode === 'dark' }"
    x-bind:aria-pressed="mode === 'dark'"
--}}
@props([
    'active' => false,
    'color' => 'primary',
    'href' => null,
    'type' => 'button',
])

@php
    $activeClass = 'ui-button-group-btn-active-'.match ($color) {
        'danger', 'error' => 'danger',
        'neutral' => 'neutral',
        default => 'primary',
    };
    $classes = ['ui-button-group-btn', $activeClass => $active];
@endphp

@if ($href)
    <a href="{{ $href }}" @if ($active) aria-current="true" @endif {{ $attributes->class($classes) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" aria-pressed="{{ $active ? 'true' : 'false' }}" {{ $attributes->class($classes) }}>{{ $slot }}</button>
@endif
