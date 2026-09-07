{{--
    <x-confirm-password-modal> — inline "sudo" password prompt for a Livewire
    component using the App\Livewire\Concerns\ConfirmsPassword trait. Composes the
    shared <x-modal> shell; the host view only needs:

        <x-confirm-password-modal />

    The trait supplies the state (confirmingPassword / confirmablePassword) and the
    confirmPassword action; closing the modal resets it via updatedConfirmingPassword().

    Props:
    - title / description: optional overrides for the heading and helper text.
--}}
@props([
    'title' => null,
    'description' => null,
])

<x-modal
    wire:model="confirmingPassword"
    :title="$title ?? __('admin.confirm_password')"
    :description="$description ?? __('admin.confirm_password_prompt')"
    submit="confirmPassword"
>
    <x-form-input
        name="confirmablePassword"
        type="password"
        :label="__('admin.password')"
        autocomplete="current-password"
        autofocus
        wire:model="confirmablePassword"
    />

    <x-slot:footer>
        <x-button.cancel x-on:click="open = false" />
        <x-button.action type="submit">{{ __('admin.confirm_password') }}</x-button.action>
    </x-slot:footer>
</x-modal>
