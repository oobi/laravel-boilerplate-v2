<div class="flex flex-col gap-6">
    {{-- Invitations is a tab within the Members section, so it keeps that section's stable header. --}}
    <x-page-header
        :title="team_trans('members.heading')"
        :description="team_trans('members.description', ['name' => $team->name])"
    />

    <x-tabs-nav :scrollable="false">
        @include('teams::livewire.team.partials.members-tabs', ['team' => $team, 'current' => 'invitations'])

        <x-slot:content>
            <livewire:teams-pending-invitations :team="$team" :key="'invitations-'.$team->id" />
        </x-slot:content>
    </x-tabs-nav>
</div>
