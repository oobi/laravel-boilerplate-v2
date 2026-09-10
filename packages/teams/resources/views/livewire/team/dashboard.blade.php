<div class="flex flex-col gap-6">
    <x-page-header
        :title="$team->name"
        :description="config('teams.labels.singular', 'Team') . ' dashboard'"
    />

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <div class="rounded-box border border-base-300 bg-base-100 p-5">
            <div class="text-sm text-base-content/60">Members</div>
            <div class="mt-1 text-3xl font-semibold text-base-content">{{ $memberCount }}</div>
        </div>
    </div>

    <div class="rounded-box border border-dashed border-base-300 p-8 text-center text-base-content/60">
        {{ __('This :label’s content will live here.', ['label' => Str::lower(config('teams.labels.singular', 'Team'))]) }}
    </div>
</div>
