<div class="flex flex-col gap-4">
    <dl class="grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
        <div>
            <dt class="text-base-content/60">{{ team_trans('ownership.primary') }}</dt>
            <dd class="font-medium">{{ $this->primaryOwner()->name }}</dd>
        </div>
        <div>
            <dt class="text-base-content/60">{{ team_trans('ownership.co_owners') }}</dt>
            <dd class="font-medium">{{ $this->coOwners()->pluck('name')->join(', ') ?: team_trans('ownership.none') }}</dd>
        </div>
    </dl>

    <x-action-list>
        {{ $this->coOwnersAction }}
        {{ $this->transferOwnershipAction }}
    </x-action-list>

    <x-filament-actions::modals />
</div>
