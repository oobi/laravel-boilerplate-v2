{{-- Shared tab items for the self-service account pages — included inside each page's <x-tabs-nav>. --}}
<x-tabs-item
    :active="request()->routeIs('profile.edit')"
    :href="route('profile.edit')"
    icon="heroicon-o-user"
>
    {{ __('admin.profile') }}
</x-tabs-item>

<x-tabs-item
    :active="request()->routeIs('profile.password')"
    :href="route('profile.password')"
    icon="heroicon-o-key"
>
    {{ __('admin.password') }}
</x-tabs-item>

<x-tabs-item
    :active="request()->routeIs('profile.two-factor')"
    :href="route('profile.two-factor')"
    icon="heroicon-o-shield-check"
>
    {{ __('admin.two_factor') }}
</x-tabs-item>
