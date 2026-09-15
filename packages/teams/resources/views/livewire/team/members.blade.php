<div class="flex flex-col gap-6">
    <x-page-header
        :title="team_trans('members.heading')"
        :description="team_trans('members.description', ['name' => $team->name])"
    />

    {{-- Invitations is a sibling tab here once member invitations are on and the viewer may invite;
         otherwise Members stands alone and the tab bar would be a lone tab, so drop it. --}}
    @php($showInvitationsTab = \Concise\Teams\Support\InvitationPolicy::membersMayInvite() && \Illuminate\Support\Facades\Gate::allows(\Concise\Teams\Enums\TeamAbility::INVITE, $team))

    @if ($showInvitationsTab)
        <x-tabs-nav :scrollable="false">
            @include('teams::livewire.team.partials.members-tabs', ['team' => $team, 'current' => 'members'])

            <x-slot:content>
                <livewire:teams-members-table :team="$team" :key="'members-'.$team->id" />
            </x-slot:content>
        </x-tabs-nav>
    @else
        <livewire:teams-members-table :team="$team" :key="'members-'.$team->id" />
    @endif
</div>
