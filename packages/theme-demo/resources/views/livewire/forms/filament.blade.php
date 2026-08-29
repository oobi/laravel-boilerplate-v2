@section('page-title', __('theme-demo::messages.filament_form_title'))

<div class="flex flex-col gap-4">
    <x-page-header :title="__('theme-demo::messages.filament_form_title')" :description="__('theme-demo::messages.filament_form_description')" />

    <form wire:submit="submit" class="card bg-base-100 border border-base-300">
        <div class="card-body">
            {{ $this->form }}
        </div>

        <div class="card-actions justify-end border-t border-base-300 p-4">
            <button type="submit" class="btn btn-primary">{{ __('Submit') }}</button>
        </div>
    </form>
</div>
