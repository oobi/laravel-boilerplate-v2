@extends('layouts.guest')

@section('content')
    <h1 class="text-xl font-semibold">{{ __('Register') }}</h1>

    @if ($errors->any())
        <div class="alert alert-error">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('register') }}" class="flex flex-col gap-4">
        @csrf

        <fieldset class="fieldset">
            <label class="label" for="name">{{ __('Name') }}</label>
            <input id="name" class="input w-full" type="text" name="name" value="{{ old('name') }}" required autofocus autocomplete="name">
        </fieldset>

        <fieldset class="fieldset">
            <label class="label" for="email">{{ __('Email') }}</label>
            <input id="email" class="input w-full" type="email" name="email" value="{{ old('email') }}" required autocomplete="username">
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

            <button type="submit" class="btn btn-primary">{{ __('Register') }}</button>
        </div>
    </form>
@endsection
