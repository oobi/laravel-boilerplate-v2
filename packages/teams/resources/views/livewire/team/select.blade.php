@php $selectAll = \Concise\Teams\Livewire\Team\SelectTeam::FILTER_ALL; @endphp
<div class="flex flex-col gap-6">
    @section('page-title', team_trans('select.title'))

    <x-page-header :title="team_trans('select.title')" :description="team_trans('select.description')">
        <x-slot:actions>
            {{ $this->createTeamAction }}
        </x-slot:actions>
    </x-page-header>

    @if ($showFilter)
        {{-- Everything here is enterable, so the useful axis is ownership: which of these am I responsible for. --}}
        <x-button-group :label="team_trans('select.title')" class="self-start">
            <x-button-group.item :active="$filter === $selectAll" wire:click="$set('filter', '{{ $selectAll }}')">
                {{ team_trans('select.all') }}
                <x-badge color="info" size="xs">{{ $allCount }}</x-badge>
            </x-button-group.item>

            <x-button-group.item :active="$filter !== $selectAll" wire:click="$set('filter', '{{ \Concise\Teams\Livewire\Team\SelectTeam::FILTER_OWNED }}')">
                {{ team_trans('select.mine') }}
                <x-badge color="info" size="xs">{{ $ownedCount }}</x-badge>
            </x-button-group.item>
        </x-button-group>
    @endif

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($teams as $team)
            <x-card :href="team_route('team.dashboard', $team)" wire:key="team-{{ $team->id }}">
                <div class="flex items-start justify-between gap-2">
                    {{-- Cards are wide, so long names wrap here rather than truncating as they must in the sidebar. --}}
                    <h2 class="font-semibold text-base-content group-hover:text-primary">{{ $team->name }}</h2>
                    <x-heroicon-o-chevron-right class="mt-0.5 h-5 w-5 shrink-0 text-base-content/40 group-hover:text-primary" />
                </div>

                <div class="flex flex-wrap items-center gap-2 text-sm text-muted">
                    @if ($team->isOwnedBy(auth()->user()))
                        <x-badge color="success">{{ team_trans('members.owner') }}</x-badge>
                    @endif

                    <span class="inline-flex items-center gap-1">
                        <x-heroicon-o-user-group class="h-4 w-4" />
                        {{ team_trans_choice('select.members', $team->users_count, ['count' => $team->users_count]) }}
                    </span>
                </div>
            </x-card>
        @endforeach
    </div>

    <x-filament-actions::modals />
</div>
