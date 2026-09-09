<div class="mx-auto max-w-lg py-12 text-center">
    <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-base-200">
        <x-heroicon-o-user-group class="h-6 w-6 text-base-content/60" />
    </div>

    <h1 class="text-xl font-semibold text-base-content">
        {{ __('You’re not part of a :label yet', ['label' => Str::lower(config('teams.labels.singular', 'Team'))]) }}
    </h1>

    <p class="mt-2 text-sm text-base-content/60">
        @if ($canCreate)
            {{ __('Create one to get started, or ask an admin to invite you.') }}
        @else
            {{ __('Ask an administrator to add you to a :label.', ['label' => Str::lower(config('teams.labels.singular', 'Team'))]) }}
        @endif
    </p>
</div>
