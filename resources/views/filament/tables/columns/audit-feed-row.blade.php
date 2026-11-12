@php
    /** @var \App\Models\AuditEntry $record */
    $record = $getRecord();
    $person = $record->causer instanceof \App\Models\User ? $record->causer : null;
    $personUrl = $person !== null && \Illuminate\Support\Facades\Gate::allows(\App\Enums\SystemPermission::VIEW_USERS->value) ? route('users.show', $person) : null;
    $sentence = \App\Support\Audit\AuditDisplay::summaryHtml($record, $personUrl);
    // What it applies to, unless the sentence says or it's the person who did it (a login).
    $subject = \App\Support\Audit\AuditDisplay::summaryNames($record, ':subject') || ($person !== null && $person->is($record->subject))
        ? null
        : \App\Support\Audit\AuditDisplay::subject($record);
    $local = \App\Support\DisplayTimezone::of($record->created_at);

    // Each address the row shows says whose it is: the person it was done to; the
    // actor's only when that's what the search matched (the sentence names them);
    // and what was typed into a failed login. Search matches all of them.
    $search ??= '';
    $subjectEmail = $record->subject instanceof \App\Models\User && ! $record->subject->is($person) ? $record->subject->email : null;
    $actorEmail = filled($search) && filled($person?->email) && str_contains(mb_strtolower($person->email), mb_strtolower(trim($search))) ? $person->email : null;
    $triedEmail = $person === null && is_string($tried = $record->getProperty('identifier')) && $tried !== '' ? $tried : null;
@endphp

{{-- One entry as a sentence. The time links to the entry; the row menu has the rest. --}}
<div class="flex w-full items-start gap-3 py-3 text-sm leading-6 sm:gap-4">
    @if ($person !== null || ($record->actor_type === \App\Support\Audit\AuditActor::USER && $record->actor_name))
        <x-avatar :user="$person" :name="$record->actor_name ?? ''" color="info" size="sm" class="mt-0.5 shrink-0 self-start" />
    @else
        <x-avatar
            :icon="\App\Support\Audit\AuditDisplay::actorIcon($record->actor_type)"
            :name="\App\Support\Audit\AuditDisplay::actorLabel($record->actor_type)"
            :color="\App\Support\Audit\AuditDisplay::actorColor($record->actor_type)->value"
            size="sm"
            class="mt-0.5 shrink-0 self-start"
        />
    @endif

    <div class="min-w-0 flex-1 whitespace-normal">
        <p class="wrap-anywhere">
            @if ($sentence !== null)
                {{ $sentence }}
            @else
                {{ $record->description }}
            @endif
        </p>

        {{-- A dot between each item after the category; ui-dot-list keeps one off the start of a wrapped line. --}}
        <div class="ui-dot-list flex flex-wrap items-center gap-x-3 gap-y-1 text-muted">
            <x-badge>{{ \App\Support\Audit\AuditDisplay::categoryLabel((string) $record->log_name) }}</x-badge>

            {{-- Who did it, when the sentence doesn't already say. --}}
            @unless (\App\Support\Audit\AuditDisplay::summaryNames($record, ':actor'))
                <span class="wrap-anywhere">{{ \App\Support\Audit\AuditDisplay::byHtml($record, $personUrl) }}</span>
            @endunless

            {{-- What it applies to, when the sentence doesn't already say. --}}
            @if ($subject !== null)
                <span class="wrap-anywhere">
                    {{ __('audit.subject') }}:
                    @if ($subject['url'])
                        <a href="{{ $subject['url'] }}" class="link link-hover font-semibold">{{ $subject['label'] }}</a>
                    @else
                        {{ $subject['label'] }}
                    @endif
                </span>
            @endif

            @if (filled($subjectEmail))
                <span class="wrap-anywhere">{{ __('audit.affected_account') }}: {{ $subjectEmail }}</span>
            @endif

            @if (filled($actorEmail))
                <span class="wrap-anywhere">{{ __('audit.done_by') }}: {{ $actorEmail }}</span>
            @endif

            @if (filled($triedEmail))
                <span class="wrap-anywhere">{{ __('audit.tried') }}: {{ $triedEmail }}</span>
            @endif

            @if ($record->impersonator_name)
                <span class="wrap-anywhere">{{ __('audit.impersonated_by') }}: {{ $record->impersonator_name }}</span>
            @endif

            @if ($record->ip_address)
                <span class="hidden sm:inline">{{ $record->ip_address }}</span>
            @endif

            {{-- On a phone the time joins this line, as the row has no room beside it. --}}
            <a href="{{ route('audit.show', $record) }}" class="link link-hover sm:hidden">
                <time datetime="{{ $record->created_at->toIso8601String() }}">{{ $local->translatedFormat('g:i a') }}</time>
            </a>
        </div>
    </div>

    <div class="hidden shrink-0 text-end sm:block">
        <a href="{{ route('audit.show', $record) }}" class="link link-hover font-medium">
            <time datetime="{{ $record->created_at->toIso8601String() }}">{{ $local->translatedFormat('g:i a') }}</time>
        </a>
        <div class="text-muted">{{ $record->created_at->diffForHumans() }}</div>
    </div>
</div>
