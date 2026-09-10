<div>
    @section('page-title', $team->name)

    @include('teams::livewire.admin.teams.partials.header', ['team' => $team, 'current' => 'members'])

    <x-tabs-nav :scrollable="false">
        @include('teams::livewire.admin.teams.partials.tabs', ['team' => $team, 'current' => 'members'])

        <x-slot:content>
            <div class="flex flex-col gap-4">
                <div class="flex justify-end">
                    {{ $this->addMemberAction }}
                </div>

                <livewire:teams-members-table :team="$team" :key="'members-'.$team->id" />
            </div>
        </x-slot:content>
    </x-tabs-nav>

    <x-filament-actions::modals />
</div>
