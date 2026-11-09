<div>
    @section('page-title', \Illuminate\Support\Str::limit($this->heading(), 60))

    <x-page-header :title="\Illuminate\Support\Str::limit($this->heading(), 120)" :description="$this->summary()">
        @if ($this->eventCode())
            <p class="mt-1 font-mono text-sm text-muted wrap-anywhere">{{ $this->eventCode() }}</p>
        @endif

        <x-slot:actions>
            <x-button.back href="{{ route('audit.index') }}">
                {{ __('audit.back_to_audit_log') }}
            </x-button.back>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        @if ($this->hasDetails())
            <div class="lg:col-span-2 flex flex-col gap-6">
                {{ $this->detailInfolist }}
            </div>
        @endif

        <div class="flex flex-col gap-6">
            {{ $this->contextInfolist }}
        </div>
    </div>
</div>
