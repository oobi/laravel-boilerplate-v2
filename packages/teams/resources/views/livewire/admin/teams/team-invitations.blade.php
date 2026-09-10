<div>
    @section('page-title', $team->name)

    @include('teams::livewire.admin.teams.partials.header', ['team' => $team, 'current' => 'invitations'])

    <x-tabs-nav :scrollable="false">
        @include('teams::livewire.admin.teams.partials.tabs', ['team' => $team, 'current' => 'invitations'])

        <x-slot:content>
            <livewire:teams-pending-invitations :team="$team" :key="'invitations-'.$team->id" />
        </x-slot:content>
    </x-tabs-nav>
</div>
