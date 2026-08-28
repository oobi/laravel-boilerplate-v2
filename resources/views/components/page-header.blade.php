{{--
    Page header — title + optional description, with an actions slot aligned
    to the right. Mirrors Buzz's <x-page-header>.
--}}
@props([
    'title',
    'description' => null,
])

<div class="ui-page-header">
    <div>
        <h1 class="ui-page-title">{!! $title !!}</h1>
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
