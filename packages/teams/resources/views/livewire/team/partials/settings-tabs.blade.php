{{-- Tabs for the team-area Settings group (Settings / Domains) — included inside each
     page's <x-tabs-nav>. `current` is passed explicitly (not request()->routeIs()) so the
     active tab survives Livewire update requests. The Domains tab shows only where the
     custom-domains tier is on and the viewer holds MANAGE_DOMAINS — the same gate its route
     and page use, so a tab never leads to a 403. Routes use team_route() to stay correct in
     both path and host mode. --}}
<x-tabs-item :active="$current === 'settings'" :href="team_route('team.settings', $team)" icon="heroicon-o-cog-6-tooth">
    {{ team_trans('nav.settings') }}
</x-tabs-item>

@if (\Concise\Teams\Support\DomainPolicy::customDomainsEnabled() && \Illuminate\Support\Facades\Gate::allows(\Concise\Teams\Enums\TeamAbility::MANAGE_DOMAINS, $team))
    <x-tabs-item :active="$current === 'domains'" :href="team_route('team.domains', $team)" icon="heroicon-o-globe-alt">
        {{ team_trans('nav.domains') }}
    </x-tabs-item>
@endif
