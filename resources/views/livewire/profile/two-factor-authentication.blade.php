<div class="flex flex-col gap-6">
    @section('page-title', __('admin.two_factor_authentication'))

    <x-page-header :title="__('admin.my_profile')" />

    <div class="w-full max-w-3xl">
        <x-tabs-nav :scrollable="false">
            @include('livewire.profile.partials.tabs')

            <x-slot:content>
                <div class="flex flex-col gap-4">
                    <div class="flex items-center justify-between gap-3">
                        <p class="ui-subtle">{{ __('admin.two_factor_info') }}</p>

                        @if (Auth::user()->two_factor_confirmed_at)
                            <x-badge color="success">{{ __('admin.enabled') }}</x-badge>
                        @else
                            <x-badge color="neutral">{{ __('admin.disabled') }}</x-badge>
                        @endif
                    </div>

                    @if ($this->enabled)
                        @if ($showingQrCode)
                            <p class="text-sm font-semibold">
                                {{ $showingConfirmation ? __('admin.two_factor_scan_qr_confirm') : __('admin.two_factor_scan_qr_enabled') }}
                            </p>

                            <div class="inline-block w-fit rounded-lg bg-white p-2">
                                {!! Auth::user()->twoFactorQrCodeSvg() !!}
                            </div>

                            <p class="text-sm font-semibold">
                                {{ __('admin.two_factor_setup_key') }}: {{ decrypt(Auth::user()->two_factor_secret) }}
                            </p>

                            @if ($showingConfirmation)
                                <fieldset class="fieldset max-w-xs">
                                    <label class="label" for="code">{{ __('admin.two_factor_code') }}</label>
                                    <input
                                        id="code"
                                        type="text"
                                        inputmode="numeric"
                                        autofocus
                                        autocomplete="one-time-code"
                                        wire:model="code"
                                        wire:keydown.enter="confirmTwoFactorAuthentication"
                                        class="input w-full"
                                    >
                                    @error('code', 'confirmTwoFactorAuthentication') <p class="text-error text-sm">{{ $message }}</p> @enderror
                                </fieldset>
                            @endif
                        @endif

                        @if ($showingRecoveryCodes)
                            <p class="text-sm font-semibold">{{ __('admin.two_factor_recovery_codes_warning') }}</p>

                            <div class="grid gap-1 rounded-lg bg-base-200 p-4 font-mono text-sm">
                                @foreach (json_decode(decrypt(Auth::user()->two_factor_recovery_codes), true) as $recoveryCode)
                                    <div>{{ $recoveryCode }}</div>
                                @endforeach
                            </div>
                        @endif
                    @endif

                    <div class="flex flex-wrap gap-3">
                        @if (! $this->enabled)
                            <x-button.action type="button" wire:click="enableTwoFactorAuthentication">
                                {{ __('admin.two_factor_enable') }}
                            </x-button.action>
                        @else
                            @if ($showingConfirmation)
                                <x-button.action type="button" wire:click="confirmTwoFactorAuthentication">
                                    {{ __('admin.two_factor_confirm') }}
                                </x-button.action>

                                <x-button.cancel type="button" wire:click="cancelSetup" />
                            @else
                                @if ($showingRecoveryCodes)
                                    <x-button type="button" wire:click="regenerateRecoveryCodes" color="neutral" variant="outline">
                                        {{ __('admin.two_factor_regenerate_recovery_codes') }}
                                    </x-button>
                                @else
                                    <x-button type="button" wire:click="showRecoveryCodes" color="neutral" variant="outline">
                                        {{ __('admin.two_factor_show_recovery_codes') }}
                                    </x-button>
                                @endif

                                <x-button.danger type="button" wire:click="disableTwoFactorAuthentication">
                                    {{ __('admin.two_factor_disable') }}
                                </x-button.danger>
                            @endif
                        @endif
                    </div>
                </div>
            </x-slot:content>
        </x-tabs-nav>
    </div>

    <x-confirm-password-modal />
</div>
