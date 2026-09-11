{{-- Shared header for the admin team pages (Overview / Members / Invitations / Settings). --}}
<x-page-header :title="$team->name" :description="team_trans('admin.page_description')">
    <x-slot:actions>
        @unless ($current === 'settings')
            <x-button.action href="{{ route('teams.settings', $team) }}">
                <x-heroicon-o-cog-6-tooth class="h-4 w-4" />
                {{ team_trans('admin.settings') }}
            </x-button.action>
        @endunless

        <x-button.back href="{{ route('teams.index') }}">
            {{ team_trans('admin.back') }}
        </x-button.back>
    </x-slot:actions>
</x-page-header>
