<div>
    @section('page-title', $user->name)

    <x-page-header :title="$user->name">
        <x-slot:actions>
            @if ($this->canEditUser())
                <x-button.action href="{{ route('users.edit', $user) }}">
                    {{ __('admin.edit_user') }}
                </x-button.action>
            @endif

            <x-button.back href="{{ route('users.index') }}">
                {{ __('admin.back_to_users') }}
            </x-button.back>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 flex flex-col gap-6">
            {{ $this->userInfolist }}

            @foreach ($this->panelsFor('main') as $panel)
                {{ $panel->render($user) }}
            @endforeach
        </div>

        <div class="flex flex-col gap-6">
            {{-- Actions — every operation on this user in one place. Lives on the
                 host component (not a registered panel) because Filament actions
                 need the HasActions component's context; see docs/panels.md. --}}
            @php
                $userActions = collect([
                    $this->impersonateAction,
                    $this->manageRolesAction,
                    $this->resetPasswordAction,
                ])->filter(fn ($action): bool => $action->isVisible());
            @endphp

            @if ($userActions->isNotEmpty())
                <x-card :title="__('admin.actions')" type="panel">
                    <x-action-list>
                        @foreach ($userActions as $action)
                            {{ $action }}
                        @endforeach
                    </x-action-list>
                </x-card>
            @endif

            @foreach ($this->panelsFor('sidebar') as $panel)
                {{ $panel->render($user) }}
            @endforeach
        </div>
    </div>

    <x-filament-actions::modals />
    <x-confirm-password-modal />
</div>
