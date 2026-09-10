{{-- The team area shell. Reuses the shared <x-app-shell> so it behaves exactly like
     the system admin (sidebar pin/drawer, header, breadcrumbs); only the navigation
     content and home link differ (R2). --}}
@php($team = current_team())

<x-app-shell
    :home="$team ? route('team.dashboard', ['team' => $team->slug]) : url('/')"
    :title="$title ?? ($team?->name ? $team->name.' · '.config('app.name') : null)"
    :breadcrumb-root="$team ? ['label' => config('teams.labels.singular', 'Team'), 'url' => null] : null"
    :breadcrumb-resource="false"
>
    <x-slot:navigation>
        @includeWhen($team !== null, 'teams::partials.team-sidebar-nav', ['team' => $team])
    </x-slot:navigation>

    {{ $slot }}
</x-app-shell>
