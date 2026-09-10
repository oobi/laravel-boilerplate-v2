{{-- Shared header for the admin team pages (Overview / Members / Invitations / Settings). --}}
<x-page-header
    :title="$team->name"
    :description="__('View and manage :label information', ['label' => Str::lower(config('teams.labels.singular', 'Team'))])"
>
    <x-slot:actions>
        @unless ($current === 'settings')
            <x-button href="{{ route('teams.settings', $team) }}" variant="soft">
                <x-heroicon-o-cog-6-tooth class="h-4 w-4" />
                {{ __('Settings') }}
            </x-button>
        @endunless

        <x-button.back href="{{ route('teams.index') }}">
            {{ __('Back to :label', ['label' => config('teams.labels.plural', 'Teams')]) }}
        </x-button.back>
    </x-slot:actions>
</x-page-header>
