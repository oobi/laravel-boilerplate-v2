{{-- The system admin shell. Chrome (sidebar pin/drawer, header, breadcrumbs) lives
     in the shared <x-app-shell>; this layout only supplies the system navigation and
     home link. The team area reuses the same shell so both work identically. --}}
<x-app-shell :home="route('dashboard')" :title="$title ?? null">
    <x-slot:navigation>
        @include('layouts.partials.admin-sidebar-nav')
    </x-slot:navigation>

    {{ $slot }}
</x-app-shell>
