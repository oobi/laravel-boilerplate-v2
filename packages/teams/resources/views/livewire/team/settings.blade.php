<div class="flex flex-col gap-6">
    <x-page-header :title="team_trans('nav.settings')" />

    <form wire:submit="save">
        {{ $this->form }}

        @if ($this->canUpdate())
            <div class="mt-6">
                <x-button.action type="submit">{{ team_trans('settings.save') }}</x-button.action>
            </div>
        @endif
    </form>

    @if ($this->canViewDomains())
        <x-card :title="team_trans('domains.title')" type="panel">
            <p class="mb-4 text-sm text-base-content/60">
                {{ team_trans('domains.description', ['name' => $team->name]) }}
            </p>

            <livewire:teams-manage-domains :team="$team" :key="'domains-'.$team->id" />
        </x-card>
    @endif

    @if ($this->canManageOwnership() || $this->canDelete())
        <x-card :title="team_trans('ownership.title')" type="panel">
            <p class="mb-4 text-sm text-base-content/60">
                {{ team_trans('ownership.description', ['name' => $team->name]) }}
            </p>

            @if ($this->canManageOwnership())
                <livewire:teams-manage-ownership :team="$team" :key="'ownership-'.$team->id" />
            @endif

            @if ($this->canDelete())
                <div @class(['border-t border-base-300 pt-4', 'mt-4' => $this->canManageOwnership()])>
                    <p class="mb-3 text-sm text-base-content/60">{{ team_trans('ownership.delete_help') }}</p>

                    <x-action-list>
                        {{ $this->deleteTeamAction }}
                    </x-action-list>
                </div>
            @endif
        </x-card>
    @endif

    <x-filament-actions::modals />
</div>
