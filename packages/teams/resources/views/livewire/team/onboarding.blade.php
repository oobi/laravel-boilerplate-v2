<div class="mx-auto max-w-lg py-12 text-center">
    @section('page-title', team_trans('onboarding.title'))

    <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-base-200">
        <x-heroicon-o-user-group class="h-6 w-6 text-base-content/60" />
    </div>

    <h1 class="text-xl font-semibold text-base-content">
        {{ team_trans('onboarding.title') }}
    </h1>

    <p class="mt-2 text-sm text-base-content/60">
        @if ($canCreate)
            {{ team_trans('onboarding.can_create') }}
        @else
            {{ team_trans('onboarding.ask_admin') }}
        @endif
    </p>

    @if ($canCreate)
        <div class="mt-6 flex justify-center">
            {{ $this->createTeamAction }}
        </div>
    @endif

    <x-filament-actions::modals />
</div>
