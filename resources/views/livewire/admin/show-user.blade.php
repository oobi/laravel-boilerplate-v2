<div>
    @section('page-title', $user->name)

    <div class="ui-page-header">
        <h1 class="ui-page-title">{{ $user->name }}</h1>

        <div class="ui-page-actions">
            <a href="{{ route('users.index') }}" class="btn btn-ghost">
                {{ __('admin.back_to_users') }}
            </a>
        </div>
    </div>

    {{ $this->userInfolist }}

    <x-filament-actions::modals />
</div>
