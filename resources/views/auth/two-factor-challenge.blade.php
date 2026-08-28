@extends('layouts.guest')

@section('content')
    <h1 class="text-xl font-semibold">{{ __('Two-Factor Confirmation') }}</h1>

    <p class="text-sm opacity-70" id="authentication-code-text">
        {{ __('Please confirm access to your account by entering the authentication code provided by your authenticator application.') }}
    </p>

    <p class="text-sm opacity-70 hidden" id="recovery-code-text">
        {{ __('Please confirm access to your account by entering one of your emergency recovery codes.') }}
    </p>

    @if ($errors->any())
        <div class="alert alert-error">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('two-factor.login') }}" class="flex flex-col gap-4">
        @csrf

        <fieldset class="fieldset" id="authentication-code-field">
            <label class="label" for="code">{{ __('Code') }}</label>
            <input id="code" class="input w-full" type="text" inputmode="numeric" name="code" autofocus autocomplete="one-time-code">
        </fieldset>

        <fieldset class="fieldset hidden" id="recovery-code-field">
            <label class="label" for="recovery_code">{{ __('Recovery Code') }}</label>
            <input id="recovery_code" class="input w-full" type="text" name="recovery_code" autocomplete="one-time-code">
        </fieldset>

        <div class="flex items-center justify-between">
            <button type="button" class="link link-hover text-sm" onclick="
                document.getElementById('authentication-code-field').classList.toggle('hidden');
                document.getElementById('recovery-code-field').classList.toggle('hidden');
                document.getElementById('authentication-code-text').classList.toggle('hidden');
                document.getElementById('recovery-code-text').classList.toggle('hidden');
            ">{{ __('Use a recovery code') }}</button>

            <button type="submit" class="btn btn-primary">{{ __('Log in') }}</button>
        </div>
    </form>
@endsection
