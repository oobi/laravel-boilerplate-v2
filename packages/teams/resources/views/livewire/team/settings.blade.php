<div class="flex flex-col gap-6">
    <x-page-header :title="team_trans('nav.settings')" />

    <x-card :title="team_trans('domains.title')" type="panel">
        <p class="mb-4 text-sm text-base-content/60">
            {{ team_trans('domains.description', ['name' => $team->name]) }}
        </p>

        <livewire:teams-manage-domains :team="$team" :key="'domains-'.$team->id" />
    </x-card>
</div>
