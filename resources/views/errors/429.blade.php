@extends('layouts.error')

@section('page-title', __('Too many tries'))

@section('content')
    <p class="ui-error-code">429</p>

    <div class="space-y-2">
        <h1 class="ui-error-title">{{ __('Too many tries') }}</h1>
        <p class="ui-error-message">{{ __('Please wait a minute, then try again.') }}</p>
    </div>

    <x-button href="{{ auth()->check() || ! Route::has('login') ? url('/') : route('login') }}" color="primary" size="lg"
        class="w-fit rounded-full">
        {{ auth()->check() || ! Route::has('login') ? __('Take me home') : __('Back to sign in') }}
        <x-heroicon-o-arrow-right class="h-4 w-4" />
    </x-button>
@endsection

@section('illustration-src', asset('images/errors/error-429.webp'))
