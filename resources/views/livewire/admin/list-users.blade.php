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

    <x-table-header>
        <x-slot:search>
            <label class="input w-full">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-4 w-4 opacity-50">
                    <circle cx="11" cy="11" r="7" fill="none" stroke="currentColor" stroke-width="1.5"/>
                    <line x1="16.5" y1="16.5" x2="21" y2="21" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                </svg>
                <input type="search" wire:model.live.debounce.300ms="tableSearch" placeholder="{{ __('admin.search_placeholder') }}">
            </label>
        </x-slot:search>

        <x-slot:filters>
            <div class="flex flex-col gap-0.5">
                <span class="text-xs text-base-content/60">{{ __('admin.system_role') }}</span>
                <select wire:model.live="tableFilters.system_role.value" class="select select-sm">
                    <option value="">{{ __('admin.all_roles') }}</option>
                    @foreach (\App\Enums\SystemRole::options() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex flex-col gap-0.5">
                <span class="text-xs text-base-content/60">{{ __('admin.status') }}</span>
                <select wire:model.live="tableFilters.active.value" class="select select-sm">
                    <option value="">{{ __('admin.all_statuses') }}</option>
                    <option value="1">{{ __('admin.active') }}</option>
                    <option value="0">{{ __('admin.inactive') }}</option>
                </select>
            </div>

            <div class="flex flex-col gap-0.5">
                <span class="text-xs text-base-content/60">{{ __('admin.status') }}</span>
                <select wire:model.live="tableFilters.trashed.value" class="select select-sm">
                    <option value="">{{ __('admin.without_trashed') }}</option>
                    <option value="1">{{ __('admin.with_trashed') }}</option>
                    <option value="0">{{ __('admin.only_trashed') }}</option>
                </select>
            </div>
        </x-slot:filters>

        <x-slot:counts>
            <span class="tooltip" data-tip="{{ __('admin.users') }}">
                <x-badge color="success" class="gap-1">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-3.5 w-3.5"><path d="M5 12.5l4 4 10-10" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    {{ $this->activeUsersCount() }}
                </x-badge>
            </span>
            <span class="tooltip" data-tip="{{ __('admin.only_trashed') }}">
                <x-badge color="error" class="gap-1">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-3.5 w-3.5"><path d="M4 7h16M9 7V5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2m-9 0 1 12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-12" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    {{ $this->trashedUsersCount() }}
                </x-badge>
            </span>
        </x-slot:counts>
    </x-table-header>

    {{ $this->table }}

    <x-filament-actions::modals />
</div>
