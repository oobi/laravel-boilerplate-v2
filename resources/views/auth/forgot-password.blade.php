@extends('layouts.guest')

@section('content')
    <h1 class="text-xl font-semibold">{{ __('Forgot your password?') }}</h1>

    <p class="text-sm opacity-70">
        {{ __("No problem. Let us know your email address and we'll email you a password reset link.") }}
    </p>

    @session('status')
        <div class="alert alert-success">{{ $value }}</div>
    @endsession

    @if ($errors->any())
        <div class="alert alert-error">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="flex flex-col gap-4">
        @csrf

        <fieldset class="fieldset">
            <label class="label" for="email">{{ __('Email') }}</label>
            <input id="email" class="input w-full" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
        </fieldset>

        <div class="flex justify-end">
            <button type="submit" class="btn btn-primary">{{ __('Email Password Reset Link') }}</button>
        </div>
    </form>
@endsection
