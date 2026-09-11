{{-- Tab items for the admin team pages — included inside each page's <x-tabs-nav>. `current` is passed
     explicitly (not request()->routeIs()) so the active tab survives Livewire update requests. --}}
<x-tabs-item :active="$current === 'show'" :href="route('teams.show', $team)" icon="heroicon-o-home">
    {{ team_trans('nav.overview') }}
</x-tabs-item>

<x-tabs-item :active="$current === 'members'" :href="route('teams.members', $team)" icon="heroicon-o-users">
    {{ team_trans('nav.members') }}
</x-tabs-item>

@if (\Concise\Teams\Support\InvitationPolicy::adminsMayInvite())
    <x-tabs-item
        :active="$current === 'invitations'"
        :href="route('teams.invitations', $team)"
        icon="heroicon-o-envelope"
        :badge="($pending = $team->invitations()->count()) > 0 ? $pending : null"
    >
        {{ team_trans('nav.invitations') }}
    </x-tabs-item>
@endif

<x-tabs-item :active="$current === 'settings'" :href="route('teams.settings', $team)" icon="heroicon-o-cog-6-tooth">
    {{ team_trans('nav.settings') }}
</x-tabs-item>
