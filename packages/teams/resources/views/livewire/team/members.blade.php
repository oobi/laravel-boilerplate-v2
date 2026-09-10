<div class="flex flex-col gap-6">
    <x-page-header
        :title="__('Members')"
        :description="__('Manage who belongs to :team and their roles.', ['team' => $team->name])"
    />

    <livewire:teams-members-table :team="$team" :key="'members-'.$team->id" />
</div>
