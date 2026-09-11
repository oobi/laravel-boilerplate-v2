<div>
    <x-table-header>
        <x-slot:search>
            <x-table-search id="invitations-search" :label="team_trans('invitations.search')" />
        </x-slot:search>

        <x-slot:actions>
            {{ $this->inviteAction }}
        </x-slot:actions>
    </x-table-header>

    {{ $this->table }}

    <x-filament-actions::modals />
</div>
