@extends('layouts.login')

@section('content')
    <h1 class="text-xl font-semibold">{{ __('Reset Password') }}</h1>

    @if ($errors->any())
        <x-alert color="error">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </x-alert>
    @endif

    <form method="POST" action="{{ route('password.update') }}" class="flex flex-col gap-4">
        @csrf

        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <fieldset class="fieldset">
            <label class="label" for="email">{{ __('Email') }}</label>
            <input id="email" class="input w-full" type="email" name="email" value="{{ old('email', $request->email) }}" required autofocus autocomplete="username">
        </fieldset>

        <fieldset class="fieldset">
            <label class="label" for="password">{{ __('Password') }}</label>
            <input id="password" class="input w-full" type="password" name="password" required autocomplete="new-password">
        </fieldset>

        <fieldset class="fieldset">
            <label class="label" for="password_confirmation">{{ __('Confirm Password') }}</label>
            <input id="password_confirmation" class="input w-full" type="password" name="password_confirmation" required autocomplete="new-password">
        </fieldset>

        <div class="flex justify-end">
            <x-button.action type="submit">{{ __('Reset Password') }}</x-button.action>
        </div>
    </form>
@endsection
