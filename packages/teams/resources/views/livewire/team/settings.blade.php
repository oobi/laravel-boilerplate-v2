<div class="flex flex-col gap-6">
    <x-page-header
        :title="team_trans('settings.heading')"
        :description="team_trans('settings.description', ['name' => $team->name])"
    />

    @php($showOwnership = $this->canManageOwnership() || $this->canDelete())
    {{-- Domains is a sibling tab here once the custom-domains tier is on and the viewer may manage
         them; otherwise Settings stands alone and the tab bar would be a lone tab, so drop it. --}}
    @php($showDomainsTab = \Concise\Teams\Support\DomainPolicy::customDomainsEnabled() && \Illuminate\Support\Facades\Gate::allows(\Concise\Teams\Enums\TeamAbility::MANAGE_DOMAINS, $team))

    @if ($showDomainsTab)
        <x-tabs-nav :scrollable="false">
            @include('teams::livewire.team.partials.settings-tabs', ['team' => $team, 'current' => 'settings'])

            <x-slot:content>
                @include('teams::livewire.team.partials.settings-body', ['team' => $team, 'showOwnership' => $showOwnership])
            </x-slot:content>
        </x-tabs-nav>
    @else
        @include('teams::livewire.team.partials.settings-body', ['team' => $team, 'showOwnership' => $showOwnership])
    @endif

    <x-filament-actions::modals />
</div>
