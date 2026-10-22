{{-- Security panel (App\Panels\Users\SecurityPanel) --}}
@php
    use App\Enums\UserAbility;
    use App\Notifications\Auth\EmailAddressChanged;
    use App\Support\Auth\GoogleLogin;
    use App\Support\TwoFactor\GracePeriod;

    // Only users the 2FA mandate applies to have a grace period worth showing.
    $graceApplies = GracePeriod::applies($user);
    $graceDays = GracePeriod::daysRemaining($user);
    $canResetGrace = GracePeriod::canBeLockedOut($user) && $user->two_factor_grace_started_at !== null;

    // Shown while Google login is on, or while a link is left over from when it was.
    $googleAccount = $user->googleAccount;
    $showGoogle = GoogleLogin::enabled() || $googleAccount !== null;
@endphp

<x-card :title="__('admin.security')" type="panel">
    <div class="flex items-center justify-between">
        <span>{{ __('admin.two_factor_authentication') }}</span>

        @if ($user->two_factor_confirmed_at)
            <x-badge color="success">{{ __('admin.enabled') }}</x-badge>
        @else
            <x-badge color="neutral">{{ __('admin.disabled') }}</x-badge>
        @endif
    </div>

    @if ($graceApplies)
        <div class="mt-2 flex items-center justify-between gap-2">
            <span>{{ __('admin.two_factor_grace') }}</span>

            @if ($user->isSuperAdmin())
                <x-badge color="neutral">{{ __('admin.two_factor_grace_super_admin') }}</x-badge>
            @elseif (GracePeriod::expired($user))
                <x-badge color="error">{{ __('admin.two_factor_grace_locked_out') }}</x-badge>
            @elseif ($user->two_factor_grace_started_at === null)
                <x-badge color="neutral">{{ __('admin.two_factor_grace_not_started') }}</x-badge>
            @else
                <x-badge :color="$graceDays <= 2 ? 'error' : 'warning'">
                    {{ trans_choice('admin.two_factor_grace_remaining', $graceDays) }}
                </x-badge>
            @endif
        </div>
    @endif

    @if ($user->trashed())
        {{-- A trashed user is restored first; ShowUser refuses panel actions on them. --}}
    @elseif ($user->two_factor_confirmed_at)
        @can(UserAbility::RESET_TWO_FACTOR_AUTHENTICATION, $user)
            <x-action-list class="mt-2">
                <x-button.danger
                    type="button"
                    wire:click="callPanelAction('security', 'force-disable-2fa')"
                    variant="soft"
                    size="sm"
                >
                    {{ __('admin.force_disable_2fa') }}
                </x-button.danger>
            </x-action-list>
        @endcan
    @elseif ($canResetGrace)
        @can(UserAbility::MANAGE_TWO_FACTOR_GRACE, $user)
            <x-action-list class="mt-2">
                <x-button.warning
                    type="button"
                    wire:click="callPanelAction('security', 'reset-2fa-grace')"
                    variant="soft"
                    size="sm"
                >
                    {{ __('admin.reset_two_factor_grace') }}
                </x-button.warning>
            </x-action-list>
        @endcan
    @endif

    @if ($showGoogle)
        <div class="mt-2 flex items-center justify-between gap-2">
            <span>{{ __('admin.google_account') }}</span>

            @if ($googleAccount)
                <x-badge color="success" class="shrink-0">{{ __('admin.linked') }}</x-badge>
            @else
                <x-badge color="neutral" class="shrink-0">{{ __('admin.not_linked') }}</x-badge>
            @endif
        </div>

        @if ($googleAccount)
            {{-- Masked like the link email: enough to recognise, not to hand out. A long domain truncates. --}}
            <p class="truncate text-sm text-muted">{{ EmailAddressChanged::mask($googleAccount->email) }}</p>
        @endif

        @if ($googleAccount && ! $user->trashed())
            @can(UserAbility::UNLINK_GOOGLE_ACCOUNT, $user)
                <x-action-list class="mt-2">
                    <x-button.danger
                        type="button"
                        wire:click="callPanelAction('security', 'unlink-google')"
                        variant="soft"
                        size="sm"
                    >
                        {{ __('admin.google_unlink') }}
                    </x-button.danger>
                </x-action-list>
            @endcan
        @endif
    @endif
</x-card>
