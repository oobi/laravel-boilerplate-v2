@extends('layouts.login')

@section('content')
    <h1 class="text-xl font-semibold">{{ __('Join :team', ['team' => $invitation->team->name]) }}</h1>
    <p class="text-sm text-base-content/60">
        {{ __('You’ve been invited to join :team on :app. Create your account to accept.', ['team' => $invitation->team->name, 'app' => config('app.name')]) }}
    </p>

    @if ($errors->any())
        <x-alert color="error">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </x-alert>
    @endif

    <form method="POST" action="{{ $action }}" class="flex flex-col gap-4">
        @csrf

        <fieldset class="fieldset">
            <label class="label" for="email">{{ __('Email') }}</label>
            {{-- The invited address: shown, not editable — the invitation is for this email. --}}
            <input id="email" class="input w-full" type="email" value="{{ $invitation->email }}" readonly autocomplete="username">
        </fieldset>

        <fieldset class="fieldset">
            <label class="label" for="first_name">{{ __('First Name') }}</label>
            <input id="first_name" class="input w-full" type="text" name="first_name" value="{{ old('first_name') }}" required autofocus autocomplete="given-name">
        </fieldset>

        <fieldset class="fieldset">
            <label class="label" for="last_name">{{ __('Last Name') }}</label>
            <input id="last_name" class="input w-full" type="text" name="last_name" value="{{ old('last_name') }}" required autocomplete="family-name">
        </fieldset>

        <fieldset class="fieldset">
            <label class="label" for="password">{{ __('Password') }}</label>
            <input id="password" class="input w-full" type="password" name="password" required autocomplete="new-password">
        </fieldset>

        <fieldset class="fieldset">
            <label class="label" for="password_confirmation">{{ __('Confirm Password') }}</label>
            <input id="password_confirmation" class="input w-full" type="password" name="password_confirmation" required autocomplete="new-password">
        </fieldset>

        <div class="flex items-center justify-between">
            <a class="link link-hover text-sm" href="{{ route('login') }}">{{ __('Already registered?') }}</a>

            <x-button.action type="submit">{{ __('Create account and join') }}</x-button.action>
        </div>
    </form>
@endsection
