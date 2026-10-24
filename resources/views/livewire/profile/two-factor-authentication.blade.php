<div class="flex flex-col gap-6">
    @section('page-title', __('admin.two_factor'))

    <x-page-header :title="__('admin.my_profile')" />

    <div class="w-full max-w-3xl">
        <x-tabs-nav>
            @include('livewire.profile.partials.tabs')

            <x-slot:content>
                <div class="flex flex-col gap-8">
                    <section class="flex flex-col gap-4" aria-labelledby="two-factor-heading">
                        <h2 id="two-factor-heading" class="font-semibold">{{ __('admin.two_factor_authentication') }}</h2>

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
                                            class="input w-full @error('code') input-error @enderror"
                                            @error('code') aria-invalid="true" aria-describedby="code-error" @enderror
                                        >
                                        <x-form-error for="code" />
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

                        @if ($this->enabled && ! $showingConfirmation && ! $mayDisable)
                            <p class="ui-subtle">{{ __('admin.two_factor_required_cannot_disable') }}</p>
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
                                        <x-button.secondary type="button" wire:click="regenerateRecoveryCodes">
                                            {{ __('admin.two_factor_regenerate_recovery_codes') }}
                                        </x-button.secondary>
                                    @else
                                        <x-button.secondary type="button" wire:click="showRecoveryCodes">
                                            {{ __('admin.two_factor_show_recovery_codes') }}
                                        </x-button.secondary>
                                    @endif

                                    @if ($mayDisable)
                                        <x-button.danger type="button" wire:click="disableTwoFactorAuthentication">
                                            {{ __('admin.two_factor_disable') }}
                                        </x-button.danger>
                                    @endif
                                @endif
                            @endif
                        </div>
                    </section>

                    {{-- Shown while the feature is on, or to let someone still linked unlink after it's switched off. --}}
                    @if ($googleEnabled || $googleAccount)
                        <section class="flex flex-col gap-4 border-t border-base-300 pt-6" aria-labelledby="google-heading">
                            <h2 id="google-heading" class="font-semibold">{{ __('admin.google_account') }}</h2>

                            <div class="flex items-center justify-between gap-3">
                                <p class="ui-subtle">
                                    {{ __('admin.google_info') }}
                                    @if ($googleAccount)
                                        {{ __('admin.google_linked_as', ['email' => $googleAccount->email]) }}
                                    @endif
                                </p>

                                @if ($googleAccount)
                                    <x-badge color="success" class="shrink-0">{{ __('admin.linked') }}</x-badge>
                                @else
                                    <x-badge color="neutral" class="shrink-0">{{ __('admin.not_linked') }}</x-badge>
                                @endif
                            </div>

                            <div class="flex flex-wrap gap-3">
                                @if ($googleAccount)
                                    <x-button.danger type="button" variant="outline" wire:click="unlinkGoogle">
                                        {{ __('admin.google_unlink') }}
                                    </x-button.danger>
                                @else
                                    <x-button.secondary type="button" wire:click="linkGoogle">
                                        {{ __('admin.google_link') }}
                                    </x-button.secondary>
                                @endif
                            </div>
                        </section>
                    @endif
                </div>
            </x-slot:content>
        </x-tabs-nav>
    </div>

    <x-confirm-password-modal />
</div>
