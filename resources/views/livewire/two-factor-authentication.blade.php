<div class="mx-auto flex max-w-2xl flex-col gap-6">
    @section('page-title', __('admin.two_factor_authentication'))

    <h1 class="ui-page-title">{{ __('admin.two_factor_authentication') }}</h1>

    <div class="card bg-base-100">
        <div class="card-body">
            <h2 class="card-title">
                @if ($this->enabled)
                    @if ($showingConfirmation)
                        {{ __('admin.two_factor_finish_enabling') }}
                    @else
                        {{ __('admin.two_factor_enabled') }}
                    @endif
                @else
                    {{ __('admin.two_factor_not_enabled') }}
                @endif
            </h2>

            <p class="ui-subtle">{{ __('admin.two_factor_info') }}</p>

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
                    <button type="button" wire:click="enableTwoFactorAuthentication" class="btn btn-primary">
                        {{ __('admin.two_factor_enable') }}
                    </button>
                @else
                    @if ($showingConfirmation)
                        <button type="button" wire:click="confirmTwoFactorAuthentication" class="btn btn-primary">
                            {{ __('admin.two_factor_confirm') }}
                        </button>
                    @elseif ($showingRecoveryCodes)
                        <button type="button" wire:click="regenerateRecoveryCodes" class="btn btn-outline">
                            {{ __('admin.two_factor_regenerate_recovery_codes') }}
                        </button>
                    @else
                        <button type="button" wire:click="showRecoveryCodes" class="btn btn-outline">
                            {{ __('admin.two_factor_show_recovery_codes') }}
                        </button>
                    @endif

                    <button type="button" wire:click="disableTwoFactorAuthentication" class="btn btn-outline btn-error">
                        {{ $showingConfirmation ? __('admin.cancel') : __('admin.two_factor_disable') }}
                    </button>
                @endif
            </div>
        </div>
    </div>
</div>
