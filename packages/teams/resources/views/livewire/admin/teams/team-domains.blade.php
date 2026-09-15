<div>
    @section('page-title', $team->name)

    @include('teams::livewire.admin.teams.partials.header', ['team' => $team, 'current' => 'domains'])

    <x-tabs-nav :scrollable="false">
        @include('teams::livewire.admin.teams.partials.tabs', ['team' => $team, 'current' => 'domains'])

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
