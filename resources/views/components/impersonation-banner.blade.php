{{--
    Fixed top banner shown while an admin is impersonating another user.
    @impersonating / @endImpersonating are Blade directives registered by
    lab404/laravel-impersonate — they check auth()->user()?->isImpersonated().
--}}
@impersonating
    <div class="ui-impersonation-banner">
        <div class="flex items-center gap-1.5">
            <x-heroicon-o-user-circle class="h-3.5 w-3.5 shrink-0" />
            <span class="font-medium">{{ __('admin.impersonating') }}</span>
            <span class="font-semibold">{{ auth()->user()->name }}</span>

            @if (! auth()->user()->active)
                <x-badge color="error" size="sm">{{ __('admin.inactive') }}</x-badge>
            @endif
        </div>

        <x-button href="{{ route('users.impersonate.leave') }}" color="neutral" size="xs" variant="outline">
            {{ __('admin.stop_impersonating') }}
        </x-button>
    </div>
@endImpersonating
