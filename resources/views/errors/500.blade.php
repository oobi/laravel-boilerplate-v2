@extends('layouts.error')

@section('content')
    <p class="ui-error-code">500</p>

    <div class="space-y-2">
        <h1 class="ui-error-title">{{ __('Oops!') }}</h1>
        <p class="ui-error-message">{{ __("Something went wrong on our end. We're already on it.") }}</p>
    </div>

    <x-button href="/" color="primary" size="lg" class="w-fit rounded-full">
        {{ __('Take me home') }}
        <x-heroicon-o-arrow-right class="h-4 w-4" />
    </x-button>
@endsection

@section('illustration-src', asset('images/errors/error-500.webp'))
