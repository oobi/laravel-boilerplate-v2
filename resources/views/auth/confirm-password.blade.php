@extends('layouts.login')

@section('page-title', __('Confirm Password'))

@section('content')
    <h1 class="text-xl font-semibold">{{ __('Confirm Password') }}</h1>

    <p class="text-sm opacity-70">
        {{ __('This is a secure area of the application. Please confirm your password before continuing.') }}
    </p>

    <x-form-errors :fields="['password']" />

    <form method="POST" action="{{ route('password.confirm') }}" class="flex flex-col gap-4">
        @csrf

        <fieldset class="fieldset">
            <label class="label" for="password">{{ __('Password') }}</label>
            <input id="password" class="input w-full @error('password') input-error @enderror" type="password" name="password" required autofocus autocomplete="current-password" @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
            <x-form-error for="password" />
        </fieldset>

        <div class="flex justify-end">
            <x-button.action type="submit">{{ __('Confirm') }}</x-button.action>
        </div>
    </form>
@endsection
