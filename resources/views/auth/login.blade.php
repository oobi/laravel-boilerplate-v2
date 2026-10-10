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
@endsection
