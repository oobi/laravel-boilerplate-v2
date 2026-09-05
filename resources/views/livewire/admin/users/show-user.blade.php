<div>
    @section('page-title', $user->name)

    <x-page-header :title="$user->name">
        <x-slot:actions>
            @if ($this->canImpersonateUser())
                <x-button color="neutral" variant="ghost" href="{{ route('users.impersonate', $user->id) }}">
                    {{ __('admin.impersonate_user') }}
                </x-button>
            @endif

            @if ($this->canEditUser())
                <x-button href="{{ route('users.edit', $user) }}">
                    {{ __('admin.edit_user') }}
                </x-button>
            @endif

            <x-button color="neutral" variant="ghost" href="{{ route('users.index') }}">
                {{ __('admin.back_to_users') }}
            </x-button>
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
            @foreach ($this->panelsFor('sidebar') as $panel)
                {{ $panel->render($user) }}
            @endforeach
        </div>
    </div>
</div>
