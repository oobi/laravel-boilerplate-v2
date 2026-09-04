@extends('layouts.guest')

@section('content')
    <h1 class="text-xl font-semibold">{{ __('Confirm Password') }}</h1>

    <p class="text-sm opacity-70">
        {{ __('This is a secure area of the application. Please confirm your password before continuing.') }}
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

    <form method="POST" action="{{ route('password.confirm') }}" class="flex flex-col gap-4">
        @csrf

        <fieldset class="fieldset">
            <label class="label" for="password">{{ __('Password') }}</label>
            <input id="password" class="input w-full" type="password" name="password" required autofocus autocomplete="current-password">
        </fieldset>

        <div class="flex justify-end">
            <x-button type="submit">{{ __('Confirm') }}</x-button>
        </div>
    </form>
@endsection
