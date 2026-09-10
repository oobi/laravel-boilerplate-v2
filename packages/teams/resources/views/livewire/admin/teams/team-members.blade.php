<div>
    @section('page-title', $team->name)

    @include('teams::livewire.admin.teams.partials.header', ['team' => $team, 'current' => 'members'])

    <x-tabs-nav :scrollable="false">
        @include('teams::livewire.admin.teams.partials.tabs', ['team' => $team, 'current' => 'members'])

        <x-slot:content>
            <livewire:teams-members-table :team="$team" :key="'members-'.$team->id" />
        </x-slot:content>
    </x-tabs-nav>
</div>
