{{-- The self-service account shell: profile, password, two-factor. Reachable by
     any signed-in, verified user regardless of area. Opened from an area (the
     admin, a team), it renders inside that area's layout so the user keeps their
     sidebar (App\Support\AccountArea\AccountAreaRegistry, which re-checks access).
     Otherwise it is sidebar-less, like the teams tier's lobby layout: :home is
     resolved via App\Support\Auth\Destination so the logo takes the viewer back
     to wherever they belong, and the column is the profile form's width (3xl),
     so the centred page reads as one column with the title over the card. --}}
@php($areaShell = \App\Support\AccountArea\AccountAreaRegistry::shellFor(auth()->user()))

@if ($areaShell !== null)
    @include($areaShell->layout, [...$areaShell->data, 'slot' => $slot, 'title' => $title ?? null])
@else
    <x-app-shell :home="\App\Support\Auth\Destination::home(auth()->user())" :title="$title ?? null" :title-context="__('admin.my_profile')" :breadcrumbs="false" width="max-w-3xl">
        {{ $slot }}
    </x-app-shell>
@endif
