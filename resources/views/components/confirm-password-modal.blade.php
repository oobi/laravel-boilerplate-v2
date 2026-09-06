{{--
    <x-confirm-password-modal> — inline "sudo" password prompt for a Livewire
    component using the App\Livewire\Concerns\ConfirmsPassword trait.

    The trait supplies the state (confirmingPassword / confirmablePassword) and
    the confirmPassword / stopConfirmingPassword actions this markup drives, so
    the host view only needs:  <x-confirm-password-modal :show="$confirmingPassword" />

    Props:
    - show: bool — whether the modal is open (bind to the trait's $confirmingPassword).
    - title / description: optional overrides for the heading and helper text.
--}}
@props([
    'show' => false,
    'title' => null,
    'description' => null,
])

<div
    @class(['modal', 'modal-open' => $show])
    role="dialog"
    aria-modal="true"
    wire:key="confirm-password-modal"
>
    <div class="modal-box flex flex-col gap-4">
        <h3 class="text-lg font-semibold">{{ $title ?? __('admin.confirm_password') }}</h3>

        <p class="ui-subtle text-sm">{{ $description ?? __('admin.confirm_password_prompt') }}</p>

        <form wire:submit="confirmPassword" class="flex flex-col gap-4">
            {{-- Only mount the field while open so the browser autofills / focuses a fresh prompt each time. --}}
            @if ($show)
                <x-form-input
                    name="confirmablePassword"
                    type="password"
                    :label="__('admin.password')"
                    autocomplete="current-password"
                    autofocus
                    wire:model="confirmablePassword"
                />
            @endif

            <div class="modal-action">
                <x-button type="button" color="neutral" variant="ghost" wire:click="stopConfirmingPassword">
                    {{ __('admin.cancel') }}
                </x-button>

                <x-button type="submit">
                    {{ __('admin.confirm_password') }}
                </x-button>
            </div>
        </form>
    </div>

    <button
        type="button"
        class="modal-backdrop"
        wire:click="stopConfirmingPassword"
        aria-label="{{ __('admin.cancel') }}"
    ></button>
</div>
