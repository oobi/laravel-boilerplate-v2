@extends('layouts.login')

@section('page-title', team_trans('invitations.expired.title'))

@section('content')
    <h1 class="text-xl font-semibold">{{ team_trans('invitations.expired.title') }}</h1>
    <p class="text-sm text-muted">
        {{ team_trans('invitations.expired.body', ['name' => $invitation->team->name]) }}
    </p>

    <a class="link link-hover text-sm" href="{{ route('login') }}">{{ team_trans('invitations.expired.sign_in') }}</a>
@endsection
