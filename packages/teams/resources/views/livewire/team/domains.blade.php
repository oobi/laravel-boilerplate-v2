<div class="flex flex-col gap-6">
    {{-- Domains is a tab within the Settings section, so it keeps that section's stable header. --}}
    <x-page-header
        :title="team_trans('settings.heading')"
        :description="team_trans('settings.description', ['name' => $team->name])"
    />

    <x-tabs-nav :scrollable="false">
        @include('teams::livewire.team.partials.settings-tabs', ['team' => $team, 'current' => 'domains'])

        <x-slot:content>
            <x-card :title="team_trans('domains.title')" type="panel">
                <p class="mb-4 text-sm text-base-content/60">
                    {{ team_trans('domains.description', ['name' => $team->name]) }}
                </p>

                <livewire:teams-manage-domains :team="$team" :key="'domains-'.$team->id" />
            </x-card>
        </x-slot:content>
    </x-tabs-nav>
</div>
