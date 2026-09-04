@section('page-title', __('theme-demo::messages.forms_daisy_title'))

<div class="flex flex-col gap-4">
    <x-page-header :title="__('theme-demo::messages.forms_daisy_title')" :description="__('theme-demo::messages.forms_daisy_description')" />

    <x-tabs-nav>
        @include('theme-demo::livewire.forms._daisy-tabs')

        <x-slot:content>
            <form wire:submit="submit" class="grid grid-cols-1 gap-6 md:grid-cols-2">
                @if ($variant === 'standard')
                    <div>
                        <label for="demo-text" class="label mb-1">{{ __('theme-demo::messages.field_text') }}</label>
                        <input id="demo-text" type="text" wire:model.blur="text" class="input w-full @error('text') input-error @enderror">
                        @error('text') <p class="mt-1 text-xs text-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="demo-email" class="label mb-1">{{ __('theme-demo::messages.field_email') }}</label>
                        <input id="demo-email" type="email" wire:model.blur="email" class="input w-full @error('email') input-error @enderror">
                        @error('email') <p class="mt-1 text-xs text-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="demo-password" class="label mb-1">{{ __('theme-demo::messages.field_password') }}</label>
                        <input id="demo-password" type="password" wire:model.blur="password" class="input w-full @error('password') input-error @enderror">
                        @error('password') <p class="mt-1 text-xs text-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="demo-number" class="label mb-1">{{ __('theme-demo::messages.field_number') }}</label>
                        <input id="demo-number" type="number" wire:model.blur="number" class="input w-full @error('number') input-error @enderror">
                        @error('number') <p class="mt-1 text-xs text-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="md:col-span-2">
                        <label for="demo-textarea" class="label mb-1">{{ __('theme-demo::messages.field_textarea') }}</label>
                        <textarea id="demo-textarea" wire:model.blur="textarea" class="textarea w-full" rows="3"></textarea>
                    </div>

                    <div>
                        <label for="demo-select" class="label mb-1">{{ __('theme-demo::messages.field_select') }}</label>
                        <select id="demo-select" wire:model="select" class="select w-full">
                            <option value="">{{ __('Choose one') }}</option>
                            <option value="one">{{ __('Option one') }}</option>
                            <option value="two">{{ __('Option two') }}</option>
                            <option value="three">{{ __('Option three') }}</option>
                        </select>
                    </div>
                @else
                    <div>
                        <div class="ui-floating-label">
                            <input id="demo-text" type="text" wire:model.blur="text" placeholder=" " class="input w-full @error('text') input-error @enderror">
                            <label for="demo-text" class="ui-floating-label-text">{{ __('theme-demo::messages.field_text') }}</label>
                        </div>
                        @error('text') <p class="mt-1 text-xs text-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <div class="ui-floating-label">
                            <input id="demo-email" type="email" wire:model.blur="email" placeholder=" " class="input w-full @error('email') input-error @enderror">
                            <label for="demo-email" class="ui-floating-label-text">{{ __('theme-demo::messages.field_email') }}</label>
                        </div>
                        @error('email') <p class="mt-1 text-xs text-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <div class="ui-floating-label">
                            <input id="demo-password" type="password" wire:model.blur="password" placeholder=" " class="input w-full @error('password') input-error @enderror">
                            <label for="demo-password" class="ui-floating-label-text">{{ __('theme-demo::messages.field_password') }}</label>
                        </div>
                        @error('password') <p class="mt-1 text-xs text-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <div class="ui-floating-label">
                            <input id="demo-number" type="number" wire:model.blur="number" placeholder=" " class="input w-full @error('number') input-error @enderror">
                            <label for="demo-number" class="ui-floating-label-text">{{ __('theme-demo::messages.field_number') }}</label>
                        </div>
                        @error('number') <p class="mt-1 text-xs text-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="ui-floating-label md:col-span-2">
                        <textarea id="demo-textarea" wire:model.blur="textarea" placeholder=" " class="textarea w-full" rows="3"></textarea>
                        <label for="demo-textarea" class="ui-floating-label-text">{{ __('theme-demo::messages.field_textarea') }}</label>
                    </div>

                    <div class="ui-floating-label">
                        <select id="demo-select" wire:model="select" class="select w-full">
                            <option value="">{{ __('Choose one') }}</option>
                            <option value="one">{{ __('Option one') }}</option>
                            <option value="two">{{ __('Option two') }}</option>
                            <option value="three">{{ __('Option three') }}</option>
                        </select>
                        <label for="demo-select" class="ui-floating-label-text">{{ __('theme-demo::messages.field_select') }}</label>
                    </div>
                @endif

                <fieldset class="fieldset">
                    <legend class="fieldset-legend">{{ __('theme-demo::messages.field_checkbox_group') }}</legend>
                    @foreach (['a' => 'Option A', 'b' => 'Option B', 'c' => 'Option C'] as $value => $label)
                        <label class="label gap-2">
                            <input type="checkbox" class="checkbox checkbox-sm" wire:model="checkboxGroup" value="{{ $value }}">
                            {{ $label }}
                        </label>
                    @endforeach
                </fieldset>

                <fieldset class="fieldset">
                    <legend class="fieldset-legend">{{ __('theme-demo::messages.field_radio_group') }}</legend>
                    @foreach (['x' => 'Option X', 'y' => 'Option Y', 'z' => 'Option Z'] as $value => $label)
                        <label class="label gap-2">
                            <input type="radio" class="radio radio-sm" wire:model="radioGroup" value="{{ $value }}">
                            {{ $label }}
                        </label>
                    @endforeach
                </fieldset>

                <label class="label gap-2">
                    <input type="checkbox" class="toggle" wire:model="toggle">
                    {{ __('theme-demo::messages.field_toggle') }}
                </label>

                <div>
                    <label class="label mb-1">{{ __('theme-demo::messages.field_range') }}</label>
                    <input type="range" min="0" max="100" wire:model.live="range" class="range">
                    <div class="mt-1 text-xs text-base-content/60">{{ $range }}</div>
                </div>

                @if ($variant === 'standard')
                    <div>
                        <label for="demo-date" class="label mb-1">{{ __('theme-demo::messages.field_date') }}</label>
                        <input id="demo-date" type="date" wire:model="date" class="input w-full">
                    </div>
                @else
                    <div class="ui-floating-label">
                        <input id="demo-date" type="date" wire:model="date" class="input w-full">
                        <label for="demo-date" class="ui-floating-label-text">{{ __('theme-demo::messages.field_date') }}</label>
                    </div>
                @endif

                <div>
                    <label class="label mb-1">{{ __('theme-demo::messages.field_file') }}</label>
                    <input type="file" wire:model="file" class="file-input w-full">
                </div>

                <div>
                    <label class="label mb-1">{{ __('theme-demo::messages.field_color') }}</label>
                    <input type="color" wire:model="color" class="input h-10 w-20 p-1">
                </div>

                @if ($variant === 'standard')
                    <div>
                        <label for="demo-error-example" class="label mb-1">{{ __('theme-demo::messages.field_error_example') }}</label>
                        <input id="demo-error-example" type="text" wire:model="errorExample" class="input input-error w-full">
                        @error('errorExample') <p class="mt-1 text-xs text-error">{{ $message }}</p> @enderror
                    </div>
                @else
                    <div>
                        <div class="ui-floating-label">
                            <input id="demo-error-example" type="text" wire:model="errorExample" placeholder=" " class="input input-error w-full">
                            <label for="demo-error-example" class="ui-floating-label-text">{{ __('theme-demo::messages.field_error_example') }}</label>
                        </div>
                        @error('errorExample') <p class="mt-1 text-xs text-error">{{ $message }}</p> @enderror
                    </div>
                @endif

                <div class="flex justify-end md:col-span-2">
                    <button type="submit" class="btn btn-primary">{{ __('Submit') }}</button>
                </div>
            </form>
        </x-slot:content>
    </x-tabs-nav>
</div>
