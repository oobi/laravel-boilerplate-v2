<div class="flex flex-col gap-6">
    <x-page-header :title="$team->name" :description="team_trans('dashboard.subtitle')" />

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <x-stats-card :label="team_trans('dashboard.members')" :value="$memberCount" />
    </div>

    <x-empty-state>{{ team_trans('dashboard.placeholder') }}</x-empty-state>
</div>
