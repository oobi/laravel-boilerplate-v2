<div class="flex flex-col gap-6">
    <x-page-header
        :title="team_trans('members.title')"
        :description="team_trans('members.description', ['name' => $team->name])"
    />

    <livewire:teams-members-table :team="$team" :key="'members-'.$team->id" />
</div>
