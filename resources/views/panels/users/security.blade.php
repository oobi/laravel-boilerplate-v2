{{-- Security panel (App\Panels\Users\SecurityPanel) --}}
<x-card :title="__('admin.security')" type="panel">
    <div class="flex items-center justify-between">
        <span>{{ __('admin.two_factor_authentication') }}</span>

        @if ($user->two_factor_confirmed_at)
            <x-badge color="success">{{ __('admin.enabled') }}</x-badge>
        @else
            <x-badge color="neutral">{{ __('admin.disabled') }}</x-badge>
        @endif
    </div>

    @if ($user->two_factor_confirmed_at)
        <x-button.danger
            type="button"
            wire:click="callPanelAction('security', 'force-disable-2fa')"
            variant="outline"
            size="sm"
            class="mt-2"
        >
            {{ __('admin.force_disable_2fa') }}
        </x-button.danger>
    @endif
</x-card>
