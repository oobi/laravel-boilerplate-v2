<div class="flex flex-col gap-6">
    @section('page-title', __('admin.my_profile'))

    <x-page-header :title="__('admin.my_profile')" />

    <div class="w-full max-w-3xl">
        <x-tabs-nav :scrollable="false">
            @include('livewire.profile.partials.tabs')

            <x-slot:content>
                <form wire:submit="updateProfileInformation" class="grid grid-cols-1 gap-8 sm:grid-cols-[10rem_1fr] sm:items-start">
                    {{-- Photo column --}}
                    <div
                        x-data="{ preview: null }"
                        x-on:livewire-upload-start="preview = null"
                        class="flex flex-col items-center gap-3 sm:items-start"
                    >
                        <template x-if="!preview">
                            <x-avatar :user="Auth::user()" size="xl" />
                        </template>

                        <div x-show="preview" x-cloak class="avatar avatar-xl">
                            <div>
                                <img :src="preview" alt="">
                            </div>
                        </div>

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
                            <x-button.danger type="button" wire:click="removeProfilePhoto" variant="outline" size="sm">
                                {{ __('admin.remove_photo') }}
                            </x-button.danger>
                        @endif

                        @error('photo') <p class="text-error text-sm">{{ $message }}</p> @enderror
                    </div>

                    {{-- Fields column --}}
                    <div class="flex flex-col gap-4">
                        <x-form-input name="first_name" :label="__('admin.first_name')" :floating="false" wire:model="first_name" required />
                        <x-form-input name="last_name" :label="__('admin.last_name')" :floating="false" wire:model="last_name" required />
                        <x-form-input name="email" type="email" :label="__('admin.email')" :floating="false" wire:model="email" required />

                        <div>
                            <x-button.action type="submit">
                                {{ __('admin.save_changes') }}
                            </x-button.action>
                        </div>
                    </div>
                </form>
            </x-slot:content>
        </x-tabs-nav>
    </div>
</div>
