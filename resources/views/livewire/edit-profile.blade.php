<div class="mx-auto flex max-w-2xl flex-col gap-6">
    @section('page-title', __('admin.my_profile'))

    <h1 class="ui-page-title">{{ __('admin.my_profile') }}</h1>

    <div class="card bg-base-100">
        <div class="card-body">
            <h2 class="card-title">{{ __('admin.profile_information') }}</h2>

            <form wire:submit="updateProfileInformation" class="flex flex-col gap-4">
                <div
                    x-data="{ preview: null }"
                    x-on:livewire-upload-start="preview = null"
                    class="flex items-center gap-4"
                >
                    <template x-if="!preview">
                        <x-avatar :name="Auth::user()->full_name" :src="Auth::user()->profile_photo_url" size="lg" />
                    </template>

                    <img x-show="preview" :src="preview" class="avatar avatar-lg" x-cloak>

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
                            <button type="button" wire:click="removeProfilePhoto" class="btn btn-sm btn-outline btn-error">
                                {{ __('admin.remove_photo') }}
                            </button>
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
                    <button type="submit" class="btn btn-primary">
                        {{ __('admin.save_changes') }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card bg-base-100">
        <div class="card-body">
            <h2 class="card-title">{{ __('admin.update_password') }}</h2>

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
                    <button type="submit" class="btn btn-primary">
                        {{ __('admin.update_password') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
