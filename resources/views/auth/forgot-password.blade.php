@extends('layouts.login')

@section('content')
    <h1 class="text-xl font-semibold">{{ __('Forgot your password?') }}</h1>

    <p class="text-sm opacity-70">
        {{ __("No problem. Let us know your email address and we'll email you a password reset link.") }}
    </p>

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

    <form method="POST" action="{{ route('password.email') }}" class="flex flex-col gap-4">
        @csrf

        <fieldset class="fieldset">
            <label class="label" for="email">{{ __('Email') }}</label>
            <input id="email" class="input w-full" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
        </fieldset>

        <div class="flex justify-end">
            <x-button type="submit">{{ __('Email Password Reset Link') }}</x-button>
        </div>
    </form>
@endsection
