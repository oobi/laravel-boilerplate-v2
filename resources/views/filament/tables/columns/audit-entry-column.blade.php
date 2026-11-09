@php
    /** @var \App\Models\AuditEntry $record */
    $record = $getRecord();
@endphp

<div class="fi-ta-text py-4 sm:min-w-0 sm:whitespace-normal">
    <a href="{{ route('audit.show', $record) }}" class="link link-hover text-sm leading-6 font-medium line-clamp-2 wrap-anywhere" title="{{ $record->description }}">
        {{ $record->description }}
    </a>
    <div class="text-sm text-muted">
        {{ \App\Support\Audit\AuditDisplay::categoryLabel((string) $record->log_name) }}
    </div>
</div>
