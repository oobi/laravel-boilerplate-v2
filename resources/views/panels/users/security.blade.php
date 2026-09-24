{{-- Security panel (App\Panels\Users\SecurityPanel) --}}
@php
    use App\Enums\UserAbility;
    use App\Support\TwoFactor\GracePeriod;

    // Only users the 2FA mandate applies to have a grace period worth showing.
    $graceApplies = GracePeriod::applies($user);
    $graceDays = GracePeriod::daysRemaining($user);
    $canResetGrace = GracePeriod::canBeLockedOut($user) && $user->two_factor_grace_started_at !== null;
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

    @if ($user->two_factor_confirmed_at)
        <x-action-list class="mt-2">
            <x-button.danger
                type="button"
                wire:click="callPanelAction('security', 'force-disable-2fa')"
                variant="outline"
                size="sm"
            >
                {{ __('admin.force_disable_2fa') }}
            </x-button.danger>
        </x-action-list>
    @elseif ($canResetGrace)
        @can(UserAbility::MANAGE_TWO_FACTOR_GRACE, $user)
            <x-action-list class="mt-2">
                <x-button.warning
                    type="button"
                    wire:click="callPanelAction('security', 'reset-2fa-grace')"
                    variant="outline"
                    size="sm"
                >
                    {{ __('admin.reset_two_factor_grace') }}
                </x-button.warning>
            </x-action-list>
        @endcan
    @endif
</x-card>
