<div>
    @section('page-title', $team->name)

    @include('teams::livewire.admin.teams.partials.header', ['team' => $team, 'current' => 'settings'])

    <x-tabs-nav :scrollable="false">
        @include('teams::livewire.admin.teams.partials.tabs', ['team' => $team, 'current' => 'settings'])

        <x-slot:content>
            @php($showDangerZone = $this->showDangerZone())

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <form wire:submit="save" @class(['lg:col-span-2' => $showDangerZone, 'lg:col-span-3' => ! $showDangerZone])>
                    {{ $this->form }}

                    @if ($this->canUpdate())
                        <div class="mt-6 flex items-center gap-4">
                            <x-button.action type="submit">
                                {{ __('admin.save_changes') }}
                            </x-button.action>
                        </div>
                    @endif
                </form>

                @if ($showDangerZone)
                    <x-card :title="team_trans('admin.danger_zone')" type="panel">
                        @if ($this->canManageOwnership())
                            <livewire:teams-manage-ownership :team="$team" :key="'ownership-'.$team->id" />
                        @endif

                        @if ($this->canDeactivate() || $this->canDelete())
                            <div @class(['border-t border-base-300 mt-4 pt-4' => $this->canManageOwnership()])>
                                <p class="mb-3 text-sm text-base-content/60">
                                    {{ team_trans('admin.danger_help') }}
                                </p>

                                <x-action-list>
                                    {{ $this->toggleActiveAction }}
                                    {{ $this->deleteTeamAction }}
                                </x-action-list>
                            </div>
                        @endif
                    </x-card>
                @endif
            </div>

        </x-slot:content>
    </x-tabs-nav>

    <x-filament-actions::modals />
</div>
