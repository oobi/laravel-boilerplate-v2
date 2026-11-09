@php
    $subject = \App\Support\Audit\AuditDisplay::subject($getRecord());
@endphp

{{-- The clamp sits on the text, not this padded cell, so no third line shows and a link's focus ring isn't clipped. --}}
<div class="fi-ta-text py-4 text-sm leading-6 sm:min-w-0 sm:whitespace-normal">
    @if ($subject === null)
        <span aria-hidden="true">–</span><span class="sr-only">{{ __('audit.none') }}</span>
    @elseif ($subject['url'])
        <a href="{{ $subject['url'] }}" class="link link-hover line-clamp-2 wrap-anywhere" title="{{ $subject['label'] }}">{{ $subject['label'] }}</a>
    @else
        <div class="line-clamp-2 wrap-anywhere" title="{{ $subject['label'] }}">{{ $subject['label'] }}</div>
    @endif
</div>
