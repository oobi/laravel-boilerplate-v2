{{-- The team-area lobby: an authenticated gateway shown before a team is chosen
     (select panel, zero-team onboarding). No sidebar — you haven't entered a team
     yet, and a plain member has no admin nav to borrow. The shared <x-app-shell>
     still gives the header, account menu (with cross-area links + logout) and the
     impersonation banner, so it's a real page, not a bare one. --}}
<x-app-shell :home="route('team.index')" :title="$title ?? null" :breadcrumbs="false">
    {{ $slot }}
</x-app-shell>
