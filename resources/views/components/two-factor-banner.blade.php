{{--
    The mandatory-2FA nag, shown on every page of the app shell to a user the
    mandate applies to (App\Support\TwoFactor\GracePeriod). Not dismissible:
    it is the only warning before login is refused. Escalates to error in the
    last two days and once the grace period has run out. A super admin gets a
    reminder without the lockout threat, since they are never locked out.
    Shown while impersonating too, so the admin sees what the user sees;
    rendering it has no side effects (the grace clock starts only at login).
--}}
@php
    use App\Support\TwoFactor\GracePeriod;

    $user = auth()->user();
    $showBanner = $user && GracePeriod::applies($user);

    if ($showBanner) {
        $days = GracePeriod::daysRemaining($user);

        [$variant, $message] = match (true) {
            $user->isSuperAdmin() => ['warning', __('auth.two_factor_required_super_admin')],
            GracePeriod::expired($user) => ['error', __('auth.two_factor_grace_ended')],
            default => [$days <= 2 ? 'error' : 'warning', trans_choice('auth.two_factor_grace_warning', $days)],
        };
    }
@endphp

@if ($showBanner)
    <x-banner :variant="$variant" role="status">
        <p>{{ $message }}</p>

        @unless (request()->routeIs('profile.two-factor'))
            <x-slot:actions>
                <x-button href="{{ route('profile.two-factor') }}" size="sm">
                    {{ __('auth.two_factor_setup_cta') }}
                </x-button>
            </x-slot:actions>
        @endunless
    </x-banner>
@endif
