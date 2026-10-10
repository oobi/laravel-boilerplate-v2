{{-- Top navigation for the public layout — basic branding only, no admin sidebar.
     Add public-facing nav links (marketing pages, docs, ...) in the middle slot below. --}}
<header class="border-b border-base-300 bg-base-100">
    <div class="ui-page flex h-16 items-center justify-between gap-4">
        <x-application-logo />

        {{-- Stub: add public nav links (e.g. About, Pricing, Contact) in a
             <nav aria-label="Main" class="hidden flex-1 items-center justify-center gap-6 md:flex">
             here. Left out while empty: an empty nav landmark is noise to a screen reader. --}}
        <div class="flex-1" aria-hidden="true"></div>

        <div class="flex items-center gap-3">
            @auth
                <span class="hidden text-sm text-base-content/70 sm:inline">
                    {{ __('Welcome, :name', ['name' => auth()->user()->first_name]) }}
                </span>

                {{-- Profile now lives inside the admin shell, so only panel users get these links. --}}
                @if (auth()->user()->canAccessAdmin())
                    @unless (\App\Support\Auth\SessionAssurance::isLow())
                        <x-button color="neutral" variant="ghost" size="sm" :href="route('profile.edit')">
                            {{ __('Profile') }}
                        </x-button>
                    @endunless

                    <x-button color="neutral" variant="ghost" size="sm" :href="route('dashboard')">
                        {{ __('Admin Dashboard') }}
                    </x-button>
                @endif

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <x-button color="neutral" variant="ghost" size="sm" type="submit">
                        <x-heroicon-o-arrow-right-on-rectangle class="size-4" />
                        {{ __('Log out') }}
                    </x-button>
                </form>
            @else
                <x-button color="neutral" variant="ghost" size="sm" :href="route('login')">
                    {{ __('Log in') }}
                </x-button>

                @if (Route::has('register'))
                    <x-button size="sm" :href="route('register')">
                        {{ __('Register') }}
                    </x-button>
                @endif
            @endauth
        </div>
    </div>
</header>
