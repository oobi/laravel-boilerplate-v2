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
        <div class="ui-button-group self-start" role="group">
            <button
                type="button"
                wire:click="$set('filter', '{{ $selectAll }}')"
                @class(['ui-button-group-btn', 'ui-button-group-btn-active-primary' => $filter === $selectAll])
            >
                {{ team_trans('select.all') }}
                <x-badge color="info" size="xs">{{ $allCount }}</x-badge>
            </button>

            <button
                type="button"
                wire:click="$set('filter', '{{ \Concise\Teams\Livewire\Team\SelectTeam::FILTER_OWNED }}')"
                @class(['ui-button-group-btn', 'ui-button-group-btn-active-primary' => $filter !== $selectAll])
            >
                {{ team_trans('select.mine') }}
                <x-badge color="info" size="xs">{{ $ownedCount }}</x-badge>
            </button>
        </div>
    @endif

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($teams as $team)
            <a
                href="{{ route('team.dashboard', ['team' => $team->slug]) }}"
                @class([
                    'group flex flex-col gap-3 rounded-box border bg-base-100 p-5 transition hover:border-primary hover:shadow-md',
                    'border-primary' => $team->id === $currentTeamId,
                    'border-base-300' => $team->id !== $currentTeamId,
                ])
                wire:key="team-{{ $team->id }}"
            >
                <div class="flex items-start justify-between gap-2">
                    {{-- Cards are wide, so long names wrap here rather than truncating as they must in the sidebar. --}}
                    <h2 class="font-semibold text-base-content group-hover:text-primary">{{ $team->name }}</h2>
                    <x-heroicon-o-chevron-right class="mt-0.5 h-5 w-5 shrink-0 text-base-content/40 group-hover:text-primary" />
                </div>

                <div class="flex flex-wrap items-center gap-2 text-sm text-base-content/60">
                    @if ($team->isOwnedBy(auth()->user()))
                        <x-badge color="success">{{ team_trans('members.owner') }}</x-badge>
                    @endif

                    <span class="inline-flex items-center gap-1">
                        <x-heroicon-o-user-group class="h-4 w-4" />
                        {{ team_trans_choice('select.members', $team->users_count, ['count' => $team->users_count]) }}
                    </span>
                </div>
            </a>
        @endforeach
    </div>

    <x-filament-actions::modals />
</div>
