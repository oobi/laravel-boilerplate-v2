{{--
    Page header: title + optional description, with an actions slot aligned
    to the right. The description wraps at a readable width beside the
    actions; they drop underneath only when they don't fit beside it.
    Mirrors Buzz's <x-page-header>.
--}}
@props([
    'title',
    'description' => null,
])

<div class="ui-page-header">
    <div class="ui-page-heading">
        <h1 class="ui-page-title">{{ $title }}</h1>
        @if ($description)
            <p class="ui-page-title-description">{{ $description }}</p>
        @endif
    </div>

    @isset($actions)
        <div class="ui-page-actions">
            {{ $actions }}
        </div>
    @endisset
</div>
