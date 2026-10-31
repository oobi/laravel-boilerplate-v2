@extends('layouts.login')

@section('page-title', __('Verify email'))

@section('content')
    <h1 class="text-xl font-semibold">{{ __('Verify email') }}</h1>

    <p class="text-sm opacity-70">
        {{ __("Thanks for signing up! Before getting started, could you verify your email address by clicking on the link we just emailed to you? If you didn't receive the email, we will gladly send you another.") }}
    </p>

    <p class="text-sm">
        {{ __('auth.verify_wrong_address') }}
        <a class="link" href="{{ route('profile.edit') }}">{{ __('auth.verify_change_address') }}</a>
    </p>

    @if (session('status') === 'verification-link-sent')
        <x-alert color="success">
            {{ __('A new verification link has been sent to the email address you provided during registration.') }}
        </x-alert>
    @endif

    <div class="flex items-center justify-between">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <x-button.action type="submit">{{ __('Resend verification email') }}</x-button.action>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <x-button color="neutral" variant="ghost" type="submit">
                <x-heroicon-o-arrow-right-on-rectangle class="size-4" />
                {{ __('Log out') }}
            </x-button>
        </form>
    </div>
@endsection
