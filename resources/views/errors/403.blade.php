@extends('layouts.error')

@section('content')
    <p class="ui-error-code">403</p>

    <div class="space-y-2">
        <h1 class="ui-error-title">{{ __('Not on the list') }}</h1>
        <p class="ui-error-message">{{ __("Whatever's behind this door, you don't have the keys.") }}</p>
    </div>

    <x-button href="/" color="primary" size="lg" class="w-fit rounded-full">
        {{ __('Take me home') }}
        <x-heroicon-o-arrow-right class="h-4 w-4" />
    </x-button>
@endsection

@section('illustration-src', asset('images/errors/error-403.webp'))
