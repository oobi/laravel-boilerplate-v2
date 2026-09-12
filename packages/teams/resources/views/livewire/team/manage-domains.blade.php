<div>
    @if ($this->canManage())
        <x-table-header>
            <x-slot:actions>
                {{ $this->addDomainAction }}
            </x-slot:actions>
        </x-table-header>
    @endif

    {{ $this->table }}

    <x-filament-actions::modals />
</div>
