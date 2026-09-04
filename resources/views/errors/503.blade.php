@extends('layouts.error')

@section('content')
    <p class="ui-error-code">503</p>

    <div class="space-y-2">
        <h1 class="text-4xl font-bold">{{ __('Be right back') }}</h1>
        <p class="max-w-sm text-lg text-base-content/60">{{ __("We're down for maintenance. Give it a moment.") }}</p>
    </div>

    <x-button href="/" color="primary" size="lg" class="w-fit rounded-full">
        {{ __('Take me home') }}
        <x-heroicon-o-arrow-right class="h-4 w-4" />
    </x-button>
@endsection

@section('illustration')
    <img src="{{ asset('images/errors/error-503.webp') }}" alt="">
@endsection
