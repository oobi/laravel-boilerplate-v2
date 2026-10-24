<div class="flex flex-col gap-6">
    @section('page-title', __('admin.profile'))

    <x-page-header :title="__('admin.my_profile')" />

    <div class="w-full max-w-3xl">
        <x-tabs-nav>
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

                        {{-- Photos wait for a verified address (#46). --}}
                        @if (Auth::user()->hasVerifiedEmail())
                            {{-- A real button opens the hidden file input, so the picker is reachable
                                 and operable from the keyboard (a hidden input isn't, and a label
                                 can't take focus; #52). Errors are announced on the button. --}}
                            <x-button.secondary
                                type="button"
                                variant="outline"
                                size="sm"
                                x-on:click="$refs.photo.click()"
                                :aria-describedby="$errors->has('photo') ? 'photo-error' : null"
                            >
                                {{ __('admin.select_new_photo') }}
                            </x-button.secondary>
                            <input
                                id="photo"
                                x-ref="photo"
                                type="file"
                                wire:model="photo"
                                x-on:change="
                                    const reader = new FileReader();
                                    reader.onload = (e) => { preview = e.target.result };
                                    reader.readAsDataURL($event.target.files[0]);
                                "
                                class="hidden"
                                tabindex="-1"
                                aria-hidden="true"
                                accept="image/png, image/jpeg"
                            >
                        @endif

                        @if (Auth::user()->profile_photo_path)
                            <x-button.danger type="button" wire:click="removeProfilePhoto" variant="outline" size="sm">
                                {{ __('admin.remove_photo') }}
                            </x-button.danger>
                        @endif

                        <x-form-error for="photo" />
                    </div>

                    {{-- Fields column --}}
                    <div class="flex flex-col gap-4">
                        <x-form-input name="first_name" :label="__('admin.first_name')" :floating="false" wire:model="first_name" required />
                        <x-form-input name="last_name" :label="__('admin.last_name')" :floating="false" wire:model="last_name" required />
                        <x-form-input name="email" type="email" :label="__('admin.email')" :floating="false" wire:model.live.debounce.400ms="email" required />

                        @if ($this->emailIsChanging)
                            <div>
                                <x-form-input
                                    name="current_password"
                                    type="password"
                                    :label="__('admin.current_password')"
                                    :floating="false"
                                    autocomplete="current-password"
                                    aria-describedby="current_password_hint"
                                    wire:model="current_password"
                                    required
                                />
                                <p id="current_password_hint" class="mt-1 text-xs text-base-content/70">{{ __('admin.email_change_password_hint') }}</p>
                            </div>
                        @endif

                        <x-form-actions>
                            <x-button.action type="submit">
                                {{ __('admin.save_changes') }}
                            </x-button.action>
                        </x-form-actions>
                    </div>
                </form>
            </x-slot:content>
        </x-tabs-nav>
    </div>
</div>
