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

                <x-card :title="__('admin.statistics')" type="panel">
                    <x-stats-list>
                        <x-stat-row icon="heroicon-o-users" :label="__('Members')" :value="$memberCount" />
                        <x-stat-row icon="heroicon-o-key" :label="__('Owners')" :value="$ownerCount" />
                        <x-stat-row icon="heroicon-o-pause-circle" :label="__('Suspended')" :value="$suspendedCount" :muted="$suspendedCount === 0" />
                        <x-stat-row icon="heroicon-o-envelope" :label="__('Pending invitations')" :value="$invitationCount" :muted="$invitationCount === 0" />
                        <x-stat-row icon="heroicon-o-calendar-days" :label="__('Created')" :value="$team->created_at?->diffForHumans()" />
                    </x-stats-list>
                </x-card>
            </div>
        </x-slot:content>
    </x-tabs-nav>
</div>
