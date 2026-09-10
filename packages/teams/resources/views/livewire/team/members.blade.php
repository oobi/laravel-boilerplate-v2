<div class="flex flex-col gap-6">
    <div>
        <h1 class="text-2xl font-semibold text-base-content">{{ __('Members') }}</h1>
        <p class="text-sm text-base-content/60">{{ __('Manage who belongs to :team and their roles.', ['team' => $team->name]) }}</p>
    </div>

    <livewire:teams-members-table :team="$team" :key="'members-'.$team->id" />
</div>
