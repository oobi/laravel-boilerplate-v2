<div class="mx-auto max-w-lg py-12 text-center">
    @section('page-title', team_trans('onboarding.title'))

    <x-avatar icon="heroicon-o-user-group" size="xl" class="mx-auto mb-4" />

    <h1 class="text-xl font-semibold text-base-content">
        {{ team_trans('onboarding.title') }}
    </h1>

    <p class="mt-2 text-sm text-muted">
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
