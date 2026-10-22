@extends('layouts.login')

@section('page-title', __('Log in'))

@section('content')
    <h1 class="text-xl font-semibold">{{ __('Log in') }}</h1>

    @session('status')
        <x-alert color="success">{{ $value }}</x-alert>
    @endsession

    {{-- Fortify files a failed or throttled sign-in under "email", but it's about the attempt, not
         the address: it goes in the box at the top. A field's own error shows under it. --}}
    <x-form-errors :fields="['password']" />

    <form method="POST" action="{{ route('login') }}" class="flex flex-col gap-4">
        @csrf

        <fieldset class="fieldset">
            <label class="label" for="email">{{ __('Email') }}</label>
            <input id="email" class="input w-full" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
        </fieldset>

        <fieldset class="fieldset">
            <label class="label" for="password">{{ __('Password') }}</label>
            <input id="password" class="input w-full @error('password') input-error @enderror" type="password" name="password" required autocomplete="current-password" @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
            <x-form-error for="password" />
        </fieldset>

        <div class="flex items-center justify-between">
            <label class="label cursor-pointer gap-2">
                <input type="checkbox" name="remember" class="checkbox checkbox-sm">
                {{ __('Remember me') }}
            </label>

            @if (Route::has('password.request'))
                <a class="link link-hover text-sm" href="{{ route('password.request') }}">{{ __('Forgot your password?') }}</a>
            @endif
        </div>

        <div class="flex items-center justify-between">
            @if (Route::has('register'))
                <a class="link link-hover text-sm" href="{{ route('register') }}">{{ __('Need an account?') }}</a>
            @endif

            <x-button.action type="submit" class="ml-auto">{{ __('Log in') }}</x-button.action>
        </div>
    </form>

    {{-- Off until Google credentials are configured (App\Support\Auth\GoogleLogin). Google's
         brand rules: its own "G" mark on a neutral button, labelled "Continue with Google". --}}
    @if (\App\Support\Auth\GoogleLogin::enabled())
        <div class="divider my-0 text-sm text-muted">{{ __('auth.google_or') }}</div>

        <x-button color="neutral" variant="outline" :href="route('google.redirect')" class="w-full gap-3">
            <svg class="size-5" viewBox="0 0 48 48" aria-hidden="true" focusable="false">
                <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
                <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
                <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
                <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
            </svg>
            {{ __('auth.google_continue') }}
        </x-button>
    @endif
@endsection
