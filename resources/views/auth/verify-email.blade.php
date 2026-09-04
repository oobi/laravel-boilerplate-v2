@extends('layouts.guest')

@section('content')
    <h1 class="text-xl font-semibold">{{ __('Verify Email') }}</h1>

    <p class="text-sm opacity-70">
        {{ __("Thanks for signing up! Before getting started, could you verify your email address by clicking on the link we just emailed to you? If you didn't receive the email, we will gladly send you another.") }}
    </p>

    @if (session('status') === 'verification-link-sent')
        <x-alert color="success">
            {{ __('A new verification link has been sent to the email address you provided during registration.') }}
        </x-alert>
    @endif

    <div class="flex items-center justify-between">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <x-button type="submit">{{ __('Resend Verification Email') }}</x-button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn btn-ghost">{{ __('Log Out') }}</button>
        </form>
    </div>
@endsection
