@extends('layouts.login')

@section('page-title', __('Forgot your password?'))

@section('content')
    <h1 class="text-xl font-semibold">{{ __('Forgot your password?') }}</h1>

    <p class="text-sm opacity-70">
        {{ __("No problem. Let us know your email address and we'll email you a password reset link.") }}
    </p>

    @session('status')
        <x-alert color="success">{{ $value }}</x-alert>
    @endsession

    <x-form-errors :fields="['email']" />

    <form method="POST" action="{{ route('password.email') }}" class="flex flex-col gap-4">
        @csrf

        <fieldset class="fieldset">
            <label class="label" for="email">{{ __('Email') }}</label>
            <input id="email" class="input w-full @error('email') input-error @enderror" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
            <x-form-error for="email" />
        </fieldset>

        <div class="flex justify-end">
            <x-button.action type="submit">{{ __('Email Password Reset Link') }}</x-button.action>
        </div>
    </form>
@endsection
