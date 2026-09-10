<div class="flex flex-col gap-6">
    <div>
        <h1 class="text-2xl font-semibold text-base-content">{{ __('Invitations') }}</h1>
        <p class="text-sm text-base-content/60">{{ __('Invite people to :team by email and manage invitations they haven’t accepted yet.', ['team' => $team->name]) }}</p>
    </div>

    <livewire:teams-pending-invitations :team="$team" :key="'invitations-'.$team->id" />
</div>
