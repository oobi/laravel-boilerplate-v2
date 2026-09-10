<div>
    @section('page-title', $team->name)

    @include('teams::livewire.admin.teams.partials.header', ['team' => $team, 'current' => 'invitations'])

    <x-tabs-nav :scrollable="false">
        @include('teams::livewire.admin.teams.partials.tabs', ['team' => $team, 'current' => 'invitations'])

        <x-slot:content>
            <div class="flex flex-col gap-4">
                <div class="flex justify-end">
                    {{ $this->inviteAction }}
                </div>

                <livewire:teams-pending-invitations :team="$team" :key="'invitations-'.$team->id" />
            </div>
        </x-slot:content>
    </x-tabs-nav>

    <x-filament-actions::modals />
</div>
