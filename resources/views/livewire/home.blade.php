<div class="mx-auto flex max-w-2xl flex-col gap-6 text-center">
    @auth
        <x-page-header :title="__('Welcome, :name!', ['name' => auth()->user()->first_name])" />
        <p class="ui-subtle">{{ __("You're logged in.") }}</p>

        @if (auth()->user()->canAccessAdmin())
            <div>
                <x-button href="{{ route('dashboard') }}" color="primary">
                    {{ __('Go to Admin Dashboard') }}
                </x-button>
            </div>
        @endif
    @else
        <x-page-header :title="__('Welcome to :app', ['app' => config('app.name')])" />
        <p class="ui-subtle">{{ __('Log in or register to get started.') }}</p>

        <div class="flex justify-center gap-3">
            <x-button href="{{ route('login') }}" color="primary">
                {{ __('Log in') }}
            </x-button>

            @if (Route::has('register'))
                <x-button href="{{ route('register') }}" variant="outline">
                    {{ __('Register') }}
                </x-button>
            @endif
        </div>
    @endauth
</div>
