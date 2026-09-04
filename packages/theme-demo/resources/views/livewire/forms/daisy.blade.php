@section('page-title', __('theme-demo::messages.forms_daisy_title'))

<div class="flex flex-col gap-4">
    <x-page-header :title="__('theme-demo::messages.forms_daisy_title')" :description="__('theme-demo::messages.forms_daisy_description')" />

    <x-tabs-nav>
        @include('theme-demo::livewire.forms._daisy-tabs')

        <x-slot:content>
            <form wire:submit="submit" class="grid grid-cols-1 gap-6 md:grid-cols-2">
                <x-form-input name="text" type="text" label="{{ __('theme-demo::messages.field_text') }}" wire:model.blur="text" :floating="$variant === 'floating'" />

                <x-form-input name="email" type="email" label="{{ __('theme-demo::messages.field_email') }}" wire:model.blur="email" :floating="$variant === 'floating'" />

                <x-form-input name="password" type="password" label="{{ __('theme-demo::messages.field_password') }}" wire:model.blur="password" :floating="$variant === 'floating'" />

                <x-form-input name="number" type="number" label="{{ __('theme-demo::messages.field_number') }}" wire:model.blur="number" :floating="$variant === 'floating'" />

                <x-form-textarea name="textarea" label="{{ __('theme-demo::messages.field_textarea') }}" wire:model.blur="textarea" rows="3" class="md:col-span-2" :floating="$variant === 'floating'" />

                <x-form-select name="select" label="{{ __('theme-demo::messages.field_select') }}" wire:model="select" :floating="$variant === 'floating'">
                    <option value="">{{ __('Choose one') }}</option>
                    <option value="one">{{ __('Option one') }}</option>
                    <option value="two">{{ __('Option two') }}</option>
                    <option value="three">{{ __('Option three') }}</option>
                </x-form-select>

                <fieldset class="fieldset">
                    <legend class="fieldset-legend ui-form-label">{{ __('theme-demo::messages.field_checkbox_group') }}</legend>
                    @foreach (['a' => 'Option A', 'b' => 'Option B', 'c' => 'Option C'] as $value => $label)
                        <label class="label gap-2 ui-form-label">
                            <input type="checkbox" class="checkbox checkbox-sm" wire:model="checkboxGroup" value="{{ $value }}">
                            {{ $label }}
                        </label>
                    @endforeach
                </fieldset>

                <fieldset class="fieldset">
                    <legend class="fieldset-legend ui-form-label">{{ __('theme-demo::messages.field_radio_group') }}</legend>
                    @foreach (['x' => 'Option X', 'y' => 'Option Y', 'z' => 'Option Z'] as $value => $label)
                        <label class="label gap-2 ui-form-label">
                            <input type="radio" class="radio radio-sm" wire:model="radioGroup" value="{{ $value }}">
                            {{ $label }}
                        </label>
                    @endforeach
                </fieldset>

                <label class="label gap-2 ui-form-label">
                    <input type="checkbox" class="toggle" wire:model="toggle">
                    {{ __('theme-demo::messages.field_toggle') }}
                </label>

                <div>
                    <label class="ui-form-label mb-1">{{ __('theme-demo::messages.field_range') }}</label>
                    <input type="range" min="0" max="100" wire:model.live="range" class="range">
                    <div class="mt-1 text-xs text-base-content/60">{{ $range }}</div>
                </div>

                <x-form-input name="date" type="date" label="{{ __('theme-demo::messages.field_date') }}" wire:model="date" :floating="$variant === 'floating'" />

                <div>
                    <label class="ui-form-label mb-1">{{ __('theme-demo::messages.field_file') }}</label>
                    <input type="file" wire:model="file" class="file-input w-full">
                </div>

                <div>
                    <label class="ui-form-label me-2 mb-1">{{ __('theme-demo::messages.field_color') }}</label>
                    <input type="color" wire:model="color" class="input h-10 w-20 p-1">
                </div>

                <x-form-input name="errorExample" type="text" label="{{ __('theme-demo::messages.field_error_example') }}" wire:model="errorExample" :floating="$variant === 'floating'" />

                <div class="flex justify-end md:col-span-2">
                    <button type="submit" class="btn btn-primary">{{ __('Submit') }}</button>
                </div>
            </form>
        </x-slot:content>
    </x-tabs-nav>
</div>
