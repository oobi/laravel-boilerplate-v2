@extends('layouts.login')

@section('page-title', __('Reset Password'))

@section('content')
    <h1 class="text-xl font-semibold">{{ __('Reset Password') }}</h1>

    {{-- An expired link or an unknown address is filed under "email" but is about the reset itself:
         it goes in the box at the top. A password's own error shows under it. --}}
    <x-form-errors :fields="['password', 'password_confirmation']" />

    <form method="POST" action="{{ route('password.update') }}" class="flex flex-col gap-4">
        @csrf

        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <fieldset class="fieldset">
            <label class="label" for="email">{{ __('Email') }}</label>
            <input id="email" class="input w-full" type="email" name="email" value="{{ old('email', $request->email) }}" required autofocus autocomplete="username">
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

        <div class="flex justify-end">
            <x-button.action type="submit">{{ __('Reset Password') }}</x-button.action>
        </div>
    </form>
@endsection
