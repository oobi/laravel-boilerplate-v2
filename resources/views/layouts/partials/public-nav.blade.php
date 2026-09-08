{{-- Top navigation for the public layout — basic branding only, no admin sidebar.
     Add public-facing nav links (marketing pages, docs, ...) in the middle slot below. --}}
<header class="border-b border-base-300 bg-base-100">
    <div class="ui-page flex h-16 items-center justify-between gap-4">
        <x-application-logo />

        {{-- Stub: add public nav links here (e.g. About, Pricing, Contact) --}}
        <nav class="hidden flex-1 items-center justify-center gap-6 md:flex">
        </nav>

        <div class="flex items-center gap-3">
            @auth
                <span class="hidden text-sm text-base-content/70 sm:inline">
                    {{ __('Welcome, :name', ['name' => auth()->user()->first_name]) }}
                </span>

                {{-- Profile now lives inside the admin shell, so only panel users get these links. --}}
                @if (auth()->user()->canAccessAdmin())
                    <a href="{{ route('profile.edit') }}" class="btn btn-ghost btn-sm">
                        {{ __('Profile') }}
                    </a>

                    <a href="{{ route('dashboard') }}" class="btn btn-ghost btn-sm">
                        {{ __('Admin Dashboard') }}
                    </a>
                @endif

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <x-button color="neutral" variant="ghost" size="sm" type="submit">
                        <x-heroicon-o-arrow-right-on-rectangle class="size-4" />
                        {{ __('Log out') }}
                    </x-button>
                </form>
            @else
                <a href="{{ route('login') }}" class="btn btn-ghost btn-sm">
                    {{ __('Log in') }}
                </a>

                @if (Route::has('register'))
                    <a href="{{ route('register') }}" class="btn btn-primary btn-sm">
                        {{ __('Register') }}
                    </a>
                @endif
            @endauth
        </div>
    </div>
</header>
