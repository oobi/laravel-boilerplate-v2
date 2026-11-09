{{--
    The audit list's Export button: a toolbar control, not a page form, so it has
    no <x-form-actions>. A real form, so the file downloads through the browser as
    it's written and never sits in Livewire's memory, however long the log is.
--}}
<form method="POST" action="{{ route('audit.export') }}">
    @csrf

    @foreach ($filters as $name => $value)
        @if (is_string($value) && $value !== '')
            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
        @endif
    @endforeach

    <x-button type="submit" variant="outline">
        {{ __('audit.export_csv') }}
    </x-button>
</form>
