@extends('layouts.login')

@section('page-title', __('Register'))

@section('content')
    <h1 class="text-xl font-semibold">{{ __('Register') }}</h1>

    <x-form-errors :fields="['first_name', 'last_name', 'email', 'password', 'password_confirmation']" />

    <form method="POST" action="{{ route('register') }}" class="flex flex-col gap-4">
        @csrf

        <fieldset class="fieldset">
            <label class="label" for="first_name">{{ __('First Name') }}</label>
            <input id="first_name" class="input w-full @error('first_name') input-error @enderror" type="text" name="first_name" value="{{ old('first_name') }}" required autofocus autocomplete="given-name" @error('first_name') aria-invalid="true" aria-describedby="first_name-error" @enderror>
            <x-form-error for="first_name" />
        </fieldset>

        <fieldset class="fieldset">
            <label class="label" for="last_name">{{ __('Last Name') }}</label>
            <input id="last_name" class="input w-full @error('last_name') input-error @enderror" type="text" name="last_name" value="{{ old('last_name') }}" required autocomplete="family-name" @error('last_name') aria-invalid="true" aria-describedby="last_name-error" @enderror>
            <x-form-error for="last_name" />
        </fieldset>

        <fieldset class="fieldset">
            <label class="label" for="email">{{ __('Email') }}</label>
            <input id="email" class="input w-full @error('email') input-error @enderror" type="email" name="email" value="{{ old('email') }}" required autocomplete="username" @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
            <x-form-error for="email" />
        </fieldset>

        <fieldset class="fieldset">
            <label class="label" for="password">{{ __('Password') }}</label>
            <input id="password" class="input w-full @error('password') input-error @enderror" type="password" name="password" required autocomplete="new-password" @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
            <x-form-error for="password" />
        </fieldset>

        <fieldset class="fieldset">
            <label class="label" for="password_confirmation">{{ __('Confirm Password') }}</label>
            <input id="password_confirmation" class="input w-full @error('password_confirmation') input-error @enderror" type="password" name="password_confirmation" required autocomplete="new-password" @error('password_confirmation') aria-invalid="true" aria-describedby="password_confirmation-error" @enderror>
            <x-form-error for="password_confirmation" />
        </fieldset>

        <div class="flex items-center justify-between">
            <a class="link link-hover text-sm" href="{{ route('login') }}">{{ __('Already registered?') }}</a>

            <x-button.action type="submit">{{ __('Register') }}</x-button.action>
        </div>
    </form>
@endsection
