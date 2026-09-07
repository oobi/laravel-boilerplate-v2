<div class="flex flex-col gap-6">
    @section('page-title', __('admin.password'))

    <x-page-header :title="__('admin.my_profile')" />

    <div class="w-full max-w-3xl">
        <x-tabs-nav :scrollable="false">
            @include('livewire.profile.partials.tabs')

            <x-slot:content>
                <form wire:submit="updatePassword" class="flex max-w-md flex-col gap-4">
                    <x-form-input
                        name="current_password"
                        type="password"
                        :label="__('admin.current_password')"
                        :floating="false"
                        autocomplete="current-password"
                        wire:model="current_password"
                    />

                    <x-form-input
                        name="password"
                        type="password"
                        :label="__('admin.new_password')"
                        :floating="false"
                        autocomplete="new-password"
                        wire:model="password"
                    />

                    <x-form-input
                        name="password_confirmation"
                        type="password"
                        :label="__('admin.confirm_password')"
                        :floating="false"
                        autocomplete="new-password"
                        wire:model="password_confirmation"
                    />

                    <div>
                        <x-button.action type="submit">
                            {{ __('admin.update_password') }}
                        </x-button.action>
                    </div>
                </form>
            </x-slot:content>
        </x-tabs-nav>
    </div>
</div>
