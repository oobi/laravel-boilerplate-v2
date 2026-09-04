@extends('layouts.error')

@section('content')
    <p class="ui-error-code">503</p>

    <div class="space-y-2">
        <h1 class="ui-error-title">{{ __('Be right back') }}</h1>
        <p class="ui-error-message">{{ __("We're doing a bit of maintenance to make things even better. Please check back soon.") }}</p>
    </div>

    <x-button href="/" color="primary" size="lg" class="w-fit rounded-full">
        {{ __('Take me home') }}
        <x-heroicon-o-arrow-right class="h-4 w-4" />
    </x-button>
@endsection

@section('illustration-src', asset('images/errors/error-503.webp'))
