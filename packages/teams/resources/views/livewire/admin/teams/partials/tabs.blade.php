{{-- Tab items for the admin team pages — included inside each page's <x-tabs-nav>. `current` is passed
     explicitly (not request()->routeIs()) so the active tab survives Livewire update requests. --}}
<x-tabs-item :active="$current === 'show'" :href="route('teams.show', $team)" icon="heroicon-o-home">
    {{ __('Overview') }}
</x-tabs-item>

<x-tabs-item :active="$current === 'members'" :href="route('teams.members', $team)" icon="heroicon-o-users">
    {{ __('Members') }}
</x-tabs-item>

<x-tabs-item
    :active="$current === 'invitations'"
    :href="route('teams.invitations', $team)"
    icon="heroicon-o-envelope"
    :badge="($pending = $team->invitations()->count()) > 0 ? $pending : null"
>
    {{ __('Invitations') }}
</x-tabs-item>

<x-tabs-item :active="$current === 'settings'" :href="route('teams.settings', $team)" icon="heroicon-o-cog-6-tooth">
    {{ __('Settings') }}
</x-tabs-item>
