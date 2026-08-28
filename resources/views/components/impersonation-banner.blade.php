{{--
    Fixed top banner shown while an admin is impersonating another user.
    @impersonating / @endImpersonating are Blade directives registered by
    lab404/laravel-impersonate — they check auth()->user()?->isImpersonated().
--}}
@impersonating
    <div class="ui-impersonation-banner">
        <div class="flex items-center gap-1.5">
            <x-heroicon-o-user-circle class="h-3.5 w-3.5 flex-shrink-0" />
            <span class="font-medium">{{ __('admin.impersonating') }}</span>
            <span class="font-semibold">{{ auth()->user()->name }}</span>

            @if (! auth()->user()->active)
                <span class="badge badge-error badge-sm">{{ __('admin.inactive') }}</span>
            @endif
        </div>

        <a href="{{ route('users.impersonate.leave') }}" class="btn btn-neutral btn-xs">
            {{ __('admin.stop_impersonating') }}
        </a>
    </div>
@endImpersonating
