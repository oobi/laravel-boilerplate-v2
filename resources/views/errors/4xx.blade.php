{{-- Fallback for any 4xx without its own view (405, 401, 402, ...). --}}
@extends('layouts.error')

@section('page-title', __("That didn't work"))

@section('content')
    <p class="ui-error-code">{{ $exception->getStatusCode() }}</p>

    <div class="space-y-2">
        <h1 class="ui-error-title">{{ __("That didn't work") }}</h1>
        <p class="ui-error-message">{{ __("We couldn't complete that request. Head home and try again.") }}</p>
    </div>

    <x-button href="/" color="primary" size="lg" class="w-fit rounded-full">
        {{ __('Take me home') }}
        <x-heroicon-o-arrow-right class="h-4 w-4" />
    </x-button>
@endsection

@section('illustration-src', asset('images/errors/error-4xx.webp'))
