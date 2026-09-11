<div class="flex flex-col gap-6">
    <x-page-header
        :title="team_trans('invitations.title')"
        :description="team_trans('invitations.description', ['name' => $team->name])"
    />

    <livewire:teams-pending-invitations :team="$team" :key="'invitations-'.$team->id" />
</div>
