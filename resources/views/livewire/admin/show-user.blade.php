<div>
    @section('page-title', $user->name)

    <div class="ui-page-header">
        <h1 class="ui-page-title">{{ $user->name }}</h1>

        <div class="ui-page-actions">
            @if ($this->canImpersonateUser())
                <a href="{{ route('users.impersonate', $user->id) }}" class="btn btn-ghost">
                    {{ __('admin.impersonate_user') }}
                </a>
            @endif

            @if ($this->canEditUser())
                <a href="{{ route('users.edit', $user) }}" class="btn btn-primary">
                    {{ __('admin.edit_user') }}
                </a>
            @endif

            <a href="{{ route('users.index') }}" class="btn btn-ghost">
                {{ __('admin.back_to_users') }}
            </a>
        </div>
    </div>

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
