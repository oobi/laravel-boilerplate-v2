<div class="flex flex-col gap-4">
    <dl class="flex flex-col gap-3 text-sm">
        <div>
            <dt class="text-base-content/60">{{ team_trans('ownership.primary') }}</dt>
            <dd class="font-medium">{{ $this->primaryOwner()->name }}</dd>
        </div>

        <div>
            <dt class="text-base-content/60">{{ team_trans('ownership.co_owners') }}</dt>
            <dd>
                @forelse ($this->coOwners() as $coOwner)
                    <div class="flex items-center justify-between gap-2">
                        <span class="font-medium">{{ $coOwner->name }}</span>
                        {{ ($this->removeCoOwnerAction)(['user' => $coOwner->id]) }}
                    </div>
                @empty
                    <span class="font-medium">{{ team_trans('ownership.none') }}</span>
                @endforelse
            </dd>
        </div>
    </dl>

    <x-action-list>
        {{ $this->addCoOwnerAction }}
        {{ $this->transferOwnershipAction }}
    </x-action-list>

    <x-filament-actions::modals />
</div>
