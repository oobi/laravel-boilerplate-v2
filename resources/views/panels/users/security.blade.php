{{-- Security panel (App\Panels\Users\SecurityPanel) --}}
<div class="card bg-base-100">
    <div class="card-body">
        <h2 class="ui-subtle">{{ __('admin.security') }}</h2>

        <div class="flex items-center justify-between">
            <span>{{ __('admin.two_factor_authentication') }}</span>

            @if ($user->two_factor_confirmed_at)
                <x-badge color="success">{{ __('admin.enabled') }}</x-badge>
            @else
                <x-badge color="neutral">{{ __('admin.disabled') }}</x-badge>
            @endif
        </div>

        @if ($user->two_factor_confirmed_at)
            <button
                type="button"
                wire:click="callPanelAction('security', 'force-disable-2fa')"
                wire:confirm="{{ __('admin.force_disable_2fa_confirm') }}"
                class="btn btn-ghost btn-sm mt-2"
            >
                {{ __('admin.force_disable_2fa') }}
            </button>
        @endif
    </div>
</div>
