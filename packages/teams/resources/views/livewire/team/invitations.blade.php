<div class="flex flex-col gap-6">
    <x-page-header
        :title="__('Invitations')"
        :description="__('Invite people to :team by email and manage invitations they haven’t accepted yet.', ['team' => $team->name])"
    />

    <livewire:teams-pending-invitations :team="$team" :key="'invitations-'.$team->id" />
</div>
