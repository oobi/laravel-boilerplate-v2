@php
    /** @var \App\Models\AuditEntry $record */
    $record = $getRecord();
    $person = $record->causer instanceof \App\Models\User ? $record->causer : null;
    $canOpen = $person !== null && \Illuminate\Support\Facades\Gate::allows(\App\Enums\SystemPermission::VIEW_USERS->value);
    // What search matches by address: the person's email now, or what was typed into a failed login.
    $email = $person?->email ?? $record->getProperty('identifier');
@endphp

<div class="fi-ta-text flex flex-col items-start gap-1 py-4 text-sm sm:min-w-0 sm:whitespace-normal">
    {{-- The system and a visitor have no name: the badge says all there is. The clamp
         sits on the link itself, so its focus ring isn't clipped. The email goes under it. --}}
    @if ($record->actor_name || (is_string($email) && $email !== ''))
        <div>
            @if ($record->actor_name && $canOpen)
                <a href="{{ route('users.show', $person) }}" class="link link-hover leading-6 line-clamp-2 wrap-anywhere" title="{{ $record->actor_name }}">{{ $record->actor_name }}</a>
            @elseif ($record->actor_name)
                <div class="leading-6 line-clamp-2 wrap-anywhere" title="{{ $record->actor_name }}">{{ $record->actor_name }}</div>
            @endif

            @if (is_string($email) && $email !== '')
                <div class="leading-6 text-muted wrap-anywhere">{{ $email }}</div>
            @endif
        </div>
    @endif

    {{-- Only a named person goes without a badge: their name says who they are. --}}
    @unless ($record->actor_type === \App\Support\Audit\AuditActor::USER && $record->actor_name)
        <x-badge :color="\App\Support\Audit\AuditDisplay::actorColor($record->actor_type)->value">
            {{ \App\Support\Audit\AuditDisplay::actorLabel($record->actor_type) }}
        </x-badge>
    @endunless

    @if ($record->impersonator_name)
        <div class="text-muted line-clamp-2 wrap-anywhere" title="{{ $record->impersonator_name }}">
            {{ __('audit.impersonated_by') }}: {{ $record->impersonator_name }}
        </div>
    @endif
</div>
