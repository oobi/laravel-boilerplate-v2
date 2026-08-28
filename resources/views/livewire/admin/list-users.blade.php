<div>
    @section('page-title', __('admin.users'))

    <div class="ui-page-header">
        <div>
            <h1 class="ui-page-title">{{ __('admin.users') }}</h1>
            <p class="ui-subtle">{{ __('admin.users_description') }}</p>
        </div>

        <div class="ui-page-actions">
            <a href="{{ route('users.create') }}" class="btn btn-primary">
                {{ __('admin.add_user') }}
            </a>
        </div>
    </div>

    @php $trashedFilterValue = data_get($tableFilters, 'trashed.value') ?? ''; @endphp

    <x-table-header>
        <x-slot:search>
            <div class="ui-floating-label" style="--ui-floating-label-inset: 2.25rem">
                <label class="input w-full">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-4 w-4 opacity-50">
                        <circle cx="11" cy="11" r="7" fill="none" stroke="currentColor" stroke-width="1.5"/>
                        <line x1="16.5" y1="16.5" x2="21" y2="21" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                    </svg>
                    <input id="users-search" type="search" wire:model.live.debounce.300ms="tableSearch" placeholder=" ">
                </label>
                <label for="users-search" class="ui-floating-label-text">{{ __('admin.search_placeholder') }}</label>
            </div>
        </x-slot:search>

        <x-slot:filters>
            <div class="ui-floating-label">
                <select id="users-system-role-filter" wire:model.live="tableFilters.system_role.value" class="select min-w-44">
                    <option value="">{{ __('admin.all_roles') }}</option>
                    @foreach (\App\Enums\SystemRole::options() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <label for="users-system-role-filter" class="ui-floating-label-text">{{ __('admin.system_role') }}</label>
            </div>

            <div class="ui-floating-label">
                <select id="users-status-filter" wire:model.live="tableFilters.active.value" class="select min-w-36">
                    <option value="">{{ __('admin.all_statuses') }}</option>
                    <option value="1">{{ __('admin.active') }}</option>
                    <option value="0">{{ __('admin.inactive') }}</option>
                </select>
                <label for="users-status-filter" class="ui-floating-label-text">{{ __('admin.status') }}</label>
            </div>
        </x-slot:filters>

        <x-slot:counts>
            <div class="ui-button-group" role="group">
                <button
                    type="button"
                    wire:click="$set('tableFilters.trashed.value', '')"
                    class="ui-button-group-btn {{ $trashedFilterValue !== '0' ? 'ui-button-group-btn-active-primary' : '' }}"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-4 w-4"><path d="M5 12.5l4 4 10-10" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    <x-badge color="{{ $trashedFilterValue !== '0' ? 'primary' : 'neutral' }}">{{ $this->activeUsersCount() }}</x-badge>
                </button>

                @if ($this->trashedUsersCount() > 0)
                    <button
                        type="button"
                        wire:click="$set('tableFilters.trashed.value', '0')"
                        class="ui-button-group-btn {{ $trashedFilterValue === '0' ? 'ui-button-group-btn-active-danger' : '' }}"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-4 w-4"><path d="M4 7h16M9 7V5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2m-9 0 1 12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-12" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        <x-badge color="{{ $trashedFilterValue === '0' ? 'error' : 'neutral' }}">{{ $this->trashedUsersCount() }}</x-badge>
                    </button>
                @endif
            </div>
        </x-slot:counts>

        @if ($trashedFilterValue === '0' && $this->trashedUsersCount() > 0)
            <x-slot:actions>
                <button
                    type="button"
                    wire:click="emptyTrash"
                    wire:confirm="{{ __('admin.empty_trash_confirm') }}"
                    class="btn btn-outline btn-error"
                >
                    {{ __('admin.empty_trash') }}
                </button>
            </x-slot:actions>
        @endif
    </x-table-header>

    {{ $this->table }}

    <x-filament-actions::modals />
</div>
