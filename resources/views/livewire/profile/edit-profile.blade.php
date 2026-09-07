<div class="flex flex-col gap-6">
    @section('page-title', __('admin.my_profile'))

    <x-page-header :title="__('admin.my_profile')" />

    <x-tabs-nav :scrollable="false">
        @include('livewire.profile.partials.tabs')

        <x-slot:content>
            <div class="mx-auto flex w-full max-w-2xl flex-col gap-6">
                <x-card :title="__('admin.profile_information')">
                    <form wire:submit="updateProfileInformation" class="flex flex-col gap-4">
                        <div
                            x-data="{ preview: null }"
                            x-on:livewire-upload-start="preview = null"
                            class="flex items-center gap-4"
                        >
                            <template x-if="!preview">
                                <x-avatar :user="Auth::user()" size="lg" />
                            </template>

                            <div x-show="preview" x-cloak class="avatar avatar-lg">
                                <div>
                                    <img :src="preview" alt="">
                                </div>
                            </div>

                            <div class="flex flex-col gap-2">
                                <label class="btn btn-sm btn-outline">
                                    {{ __('admin.select_new_photo') }}
                                    <input
                                        type="file"
                                        wire:model="photo"
                                        x-on:change="
                                            const reader = new FileReader();
                                            reader.onload = (e) => { preview = e.target.result };
                                            reader.readAsDataURL($event.target.files[0]);
                                        "
                                        class="hidden"
                                        accept="image/png, image/jpeg"
                                    >
                                </label>

                                @if (Auth::user()->profile_photo_path)
                                    <x-button type="button" wire:click="removeProfilePhoto" color="error" variant="outline" size="sm">
                                        {{ __('admin.remove_photo') }}
                                    </x-button>
                                @endif

                                @error('photo') <p class="text-error text-sm">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <fieldset class="fieldset">
                            <label class="label" for="first_name">{{ __('admin.first_name') }}</label>
                            <input id="first_name" type="text" wire:model="first_name" class="input w-full" required>
                            @error('first_name') <p class="text-error text-sm">{{ $message }}</p> @enderror
                        </fieldset>

                        <fieldset class="fieldset">
                            <label class="label" for="last_name">{{ __('admin.last_name') }}</label>
                            <input id="last_name" type="text" wire:model="last_name" class="input w-full" required>
                            @error('last_name') <p class="text-error text-sm">{{ $message }}</p> @enderror
                        </fieldset>

                        <fieldset class="fieldset">
                            <label class="label" for="email">{{ __('admin.email') }}</label>
                            <input id="email" type="email" wire:model="email" class="input w-full" required>
                            @error('email') <p class="text-error text-sm">{{ $message }}</p> @enderror
                        </fieldset>

                        <div>
                            <x-button type="submit">
                                {{ __('admin.save_changes') }}
                            </x-button>
                        </div>
                    </form>
                </x-card>
            </div>
        </x-slot:content>
    </x-tabs-nav>
</div>
