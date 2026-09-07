<div class="flex flex-col gap-6">
    @section('page-title', __('admin.password'))

    <x-page-header :title="__('admin.my_profile')" />

    <x-tabs-nav :scrollable="false">
        @include('livewire.profile.partials.tabs')

        <x-slot:content>
            <div class="mx-auto flex w-full max-w-2xl flex-col gap-6">
                <x-card :title="__('admin.update_password')">
                    <form wire:submit="updatePassword" class="flex flex-col gap-4">
                        <fieldset class="fieldset">
                            <label class="label" for="current_password">{{ __('admin.current_password') }}</label>
                            <input id="current_password" type="password" wire:model="current_password" class="input w-full" autocomplete="current-password">
                            @error('current_password') <p class="text-error text-sm">{{ $message }}</p> @enderror
                        </fieldset>

                        <fieldset class="fieldset">
                            <label class="label" for="password">{{ __('admin.new_password') }}</label>
                            <input id="password" type="password" wire:model="password" class="input w-full" autocomplete="new-password">
                            @error('password') <p class="text-error text-sm">{{ $message }}</p> @enderror
                        </fieldset>

                        <fieldset class="fieldset">
                            <label class="label" for="password_confirmation">{{ __('admin.confirm_password') }}</label>
                            <input id="password_confirmation" type="password" wire:model="password_confirmation" class="input w-full" autocomplete="new-password">
                        </fieldset>

                        <div>
                            <x-button type="submit">
                                {{ __('admin.update_password') }}
                            </x-button>
                        </div>
                    </form>
                </x-card>
            </div>
        </x-slot:content>
    </x-tabs-nav>
</div>
