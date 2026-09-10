<div>
    @section('page-title', $team->name)

    @include('teams::livewire.admin.teams.partials.header', ['team' => $team, 'current' => 'settings'])

    <x-tabs-nav :scrollable="false">
        @include('teams::livewire.admin.teams.partials.tabs', ['team' => $team, 'current' => 'settings'])

        <x-slot:content>
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <form wire:submit="save" class="lg:col-span-2">
                    {{ $this->form }}

                    <div class="mt-6 flex items-center gap-4">
                        <x-button.action type="submit">
                            {{ __('admin.save_changes') }}
                        </x-button.action>
                    </div>
                </form>

                <x-card :title="__('Danger zone')" type="panel">
                    <p class="text-sm text-base-content/60">
                        {{ __('Deactivate first, then delete. A deleted :label can be restored from the trash.', ['label' => Str::lower(config('teams.labels.singular', 'Team'))]) }}
                    </p>

                    <x-action-list>
                        {{ $this->toggleActiveAction }}
                        {{ $this->deleteTeamAction }}
                    </x-action-list>
                </x-card>
            </div>
        </x-slot:content>
    </x-tabs-nav>

    <x-filament-actions::modals />
</div>
