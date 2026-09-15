{{-- Tabs for the team-area Members group (Members / Invitations) — included inside each
     page's <x-tabs-nav>. `current` is passed explicitly (not request()->routeIs()) so the
     active tab survives Livewire update requests. The Invitations tab shows only where the
     member-invitations switch is on and the viewer holds INVITE — the same gate its route
     and page use, so a tab never leads to a 403. Routes use team_route() to stay correct in
     both path and host mode. --}}
<x-tabs-item :active="$current === 'members'" :href="team_route('team.members', $team)" icon="heroicon-o-users">
    {{ team_trans('nav.members') }}
</x-tabs-item>

@if (\Concise\Teams\Support\InvitationPolicy::membersMayInvite() && \Illuminate\Support\Facades\Gate::allows(\Concise\Teams\Enums\TeamAbility::INVITE, $team))
    <x-tabs-item
        :active="$current === 'invitations'"
        :href="team_route('team.invitations', $team)"
        icon="heroicon-o-envelope"
        :badge="($pending = $team->invitations()->count()) > 0 ? $pending : null"
    >
        {{ team_trans('nav.invitations') }}
    </x-tabs-item>
@endif
