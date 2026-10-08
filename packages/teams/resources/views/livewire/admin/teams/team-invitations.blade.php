<div>
    @section('page-parent', $team->name)
    @section('page-parent-url', route('teams.show', $team))
    @section('page-title', team_trans('nav.invitations'))

    @include('teams::livewire.admin.teams.partials.header', ['team' => $team, 'current' => 'invitations'])

    <x-tabs-nav :scrollable="false">
        @include('teams::livewire.admin.teams.partials.tabs', ['team' => $team, 'current' => 'invitations'])

        <x-slot:content>
            <livewire:teams-pending-invitations :team="$team" :key="'invitations-'.$team->id" />
        </x-slot:content>
    </x-tabs-nav>
</div>
