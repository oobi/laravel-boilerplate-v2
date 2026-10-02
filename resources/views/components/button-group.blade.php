{{--
    <x-button-group>: a segmented control: a row of <x-button-group.item>s
    sharing borders, one (or more) active. The one way to group buttons; never
    daisyUI's `join`. For a form field's single choice in a Filament form, use
    ToggleButtons::make()->grouped() instead, which is drawn to match.

    Props:
    - label: what the group chooses, for screen readers (aria-label)
    - block: full width, each segment an equal share (default: false)
    - quiet: no frame or shadow, segments muted until hovered or chosen; for
      inside a menu or toolbar (default: false)
    - size: sm, or null for the field height (default: null)
--}}
@props([
    'label' => null,
    'block' => false,
    'quiet' => false,
    'size' => null,
])

<div
    role="group"
    @if ($label) aria-label="{{ $label }}" @endif
    {{ $attributes->class([
        'ui-button-group',
        'ui-button-group-block' => $block,
        'ui-button-group-quiet' => $quiet,
        'ui-button-group-sm' => $size === 'sm',
    ]) }}
>
    {{ $slot }}
</div>
