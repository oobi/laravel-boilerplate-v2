{{-- The self-service account shell: profile, password, two-factor. Reachable by
     any signed-in, verified user regardless of area — a system admin and a team
     member land on the same page. No sidebar (there's no area-specific nav to
     borrow here); :home is resolved via App\Support\Auth\Destination so the logo
     takes the viewer back to wherever they belong (their admin dashboard, their
     team, or the public landing). Shares the same sidebar-less shape as the
     teams tier's lobby layout. --}}
<x-app-shell :home="\App\Support\Auth\Destination::home(auth()->user())" :title="$title ?? null" :breadcrumbs="false">
    {{ $slot }}
</x-app-shell>
