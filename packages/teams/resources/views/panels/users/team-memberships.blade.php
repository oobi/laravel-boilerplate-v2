{{-- Team memberships panel (Concise\Teams\Panels\Users\TeamMembershipsPanel) --}}
<x-card :title="team_trans('memberships.title')" type="panel">
    @if ($memberships->isEmpty())
        <p class="text-sm text-base-content/60">
            {{ team_trans('memberships.none') }}
        </p>
    @else
        <ul class="divide-y divide-base-300">
            @foreach ($memberships as $membership)
                <li class="flex items-center justify-between gap-3 py-2">
                    @if ($canManage)
                        <a href="{{ route('teams.show', $membership['team']) }}" class="link link-hover truncate text-sm font-medium">
                            {{ $membership['team']->name }}
                        </a>
                    @else
                        <span class="truncate text-sm font-medium text-base-content">{{ $membership['team']->name }}</span>
                    @endif

                    <div class="flex shrink-0 flex-wrap justify-end gap-1">
                        @foreach ($membership['badges'] as $badge)
                            <x-badge :color="$badge['color']">{{ $badge['label'] }}</x-badge>
                        @endforeach
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</x-card>
