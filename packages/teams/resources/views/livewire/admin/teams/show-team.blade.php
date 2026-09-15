<div>
    @section('page-title', $team->name)

    @include('teams::livewire.admin.teams.partials.header', ['team' => $team, 'current' => 'show'])

    <x-tabs-nav :scrollable="false">
        @include('teams::livewire.admin.teams.partials.tabs', ['team' => $team, 'current' => 'show'])

        <x-slot:content>
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div class="lg:col-span-2">
                    {{ $this->teamInfolist }}
                </div>

                {{-- Right rail: the team URL (a panel matching Statistics) above the
                     stats, with a gap between them. Rendered here rather than in the
                     infolist so the open icon sits right after the value. --}}
                <div class="space-y-6">
                    <x-card :title="team_trans('admin.url')" type="panel">
                        <div class="flex items-center gap-1.5 text-sm">
                            <span class="break-all text-base-content">{{ team_route('team.dashboard', $team) }}</span>
                            {{ $this->openTeamAction }}
                        </div>
                    </x-card>

                    {{-- A peer of the Filament section beside it (the tabs island is a page container, not a card),
                         and the same treatment as the Settings tab's Danger zone — sibling tabs match. --}}
                    <x-card :title="__('admin.statistics')" type="panel">
                        <x-stats-list>
                            <x-stat-row icon="heroicon-o-users" :label="team_trans('admin.members')" :value="$memberCount" />
                            <x-stat-row icon="heroicon-o-key" :label="team_trans('admin.owners')" :value="$ownerCount" />
                            <x-stat-row icon="heroicon-o-pause-circle" :label="team_trans('admin.suspended')" :value="$suspendedCount" :muted="$suspendedCount === 0" />
                            @if ($showInvitations)
                                <x-stat-row icon="heroicon-o-envelope" :label="team_trans('admin.pending_invitations')" :value="$invitationCount" :muted="$invitationCount === 0" />
                            @endif
                            <x-stat-row icon="heroicon-o-calendar-days" :label="team_trans('admin.created')" :value="$team->created_at?->diffForHumans()" />
                        </x-stats-list>
                    </x-card>
                </div>
            </div>
        </x-slot:content>
    </x-tabs-nav>

    <x-filament-actions::modals />
</div>
