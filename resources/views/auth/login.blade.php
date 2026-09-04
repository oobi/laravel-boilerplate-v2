@extends('layouts.login')

@section('content')
    <h1 class="text-xl font-semibold">{{ __('Log in') }}</h1>

    @session('status')
        <x-alert color="success">{{ $value }}</x-alert>
    @endsession

    @if ($errors->any())
        <x-alert color="error">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </x-alert>
    @endif

    <form method="POST" action="{{ route('login') }}" class="flex flex-col gap-4">
        @csrf

        <fieldset class="fieldset">
            <label class="label" for="email">{{ __('Email') }}</label>
            <input id="email" class="input w-full" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
        </fieldset>

        <fieldset class="fieldset">
            <label class="label" for="password">{{ __('Password') }}</label>
            <input id="password" class="input w-full" type="password" name="password" required autocomplete="current-password">
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

            <x-button type="submit" class="ml-auto">{{ __('Log in') }}</x-button>
        </div>
    </form>
@endsection
