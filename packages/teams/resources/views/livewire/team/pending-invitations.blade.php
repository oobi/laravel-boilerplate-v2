<div>
    <x-table-header>
        <x-slot:search>
            <x-table-search id="invitations-search" :label="__('Search invitations…')" />
        </x-slot:search>
    </x-table-header>

    {{ $this->table }}

    <x-filament-actions::modals />
</div>
