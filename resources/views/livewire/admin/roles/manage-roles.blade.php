<div>
    @section('page-title', __('admin.roles'))

    <x-page-header :title="__('admin.roles')" :description="$scope->description()">
        <x-slot:actions>
            <x-button href="{{ $createUrl }}">
                {{ __('admin.add_role') }}
            </x-button>

            {{ $this->deleteRoleAction }}
        </x-slot:actions>
    </x-page-header>

    @if ($tabs->isNotEmpty())
        {{-- One tab per registered RoleScope (core's system scope plus any add-on's). --}}
        <x-tabs-nav :scrollable="false">
            @foreach ($tabs as $tab)
                <x-tabs-item :active="$tab['active']" :href="$tab['href']" :badge="$tab['badge']">
                    {{ $tab['label'] }}
                </x-tabs-item>
            @endforeach

            <x-slot:content>
                @include('livewire.admin.roles.partials.editor', ['form' => $this->form])
            </x-slot:content>
        </x-tabs-nav>
    @else
        @include('livewire.admin.roles.partials.editor', ['form' => $this->form])
    @endif

    <x-filament-actions::modals />
</div>
