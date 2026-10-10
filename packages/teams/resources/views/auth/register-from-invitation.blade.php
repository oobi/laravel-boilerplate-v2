@extends('layouts.login')

@section('page-title', team_trans('invitations.register.title', ['name' => $invitation->team->name]))

@section('content')
    <h1 class="text-xl font-semibold">{{ team_trans('invitations.register.title', ['name' => $invitation->team->name]) }}</h1>
    <p class="text-sm text-muted">
        {{ team_trans('invitations.register.intro', ['name' => $invitation->team->name, 'app' => config('app.name')]) }}
    </p>

    <x-form-errors :fields="['first_name', 'last_name', 'password', 'password_confirmation']" />

    <form method="POST" action="{{ $action }}" class="flex flex-col gap-4">
        @csrf

        <fieldset class="fieldset">
            <label class="label" for="email">{{ team_trans('invitations.register.email') }}</label>
            {{-- The invited address: shown, not editable — the invitation is for this email. --}}
            <input id="email" class="input w-full" type="email" value="{{ $invitation->email }}" readonly autocomplete="username">
        </fieldset>

        <fieldset class="fieldset">
            <label class="label" for="first_name">{{ team_trans('invitations.register.first_name') }}</label>
            <input id="first_name" class="input w-full @error('first_name') input-error @enderror" type="text" name="first_name" value="{{ old('first_name') }}" required autofocus autocomplete="given-name" @error('first_name') aria-invalid="true" aria-describedby="first_name-error" @enderror>
            <x-form-error for="first_name" />
        </fieldset>

        <fieldset class="fieldset">
            <label class="label" for="last_name">{{ team_trans('invitations.register.last_name') }}</label>
            <input id="last_name" class="input w-full @error('last_name') input-error @enderror" type="text" name="last_name" value="{{ old('last_name') }}" required autocomplete="family-name" @error('last_name') aria-invalid="true" aria-describedby="last_name-error" @enderror>
            <x-form-error for="last_name" />
        </fieldset>

        <fieldset class="fieldset">
            <label class="label" for="password">{{ team_trans('invitations.register.password') }}</label>
            <input id="password" class="input w-full @error('password') input-error @enderror" type="password" name="password" required autocomplete="new-password" @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
            <x-form-error for="password" />
        </fieldset>

        <fieldset class="fieldset">
            <label class="label" for="password_confirmation">{{ team_trans('invitations.register.confirm_password') }}</label>
            <input id="password_confirmation" class="input w-full @error('password_confirmation') input-error @enderror" type="password" name="password_confirmation" required autocomplete="new-password" @error('password_confirmation') aria-invalid="true" aria-describedby="password_confirmation-error" @enderror>
            <x-form-error for="password_confirmation" />
        </fieldset>

        <div class="flex items-center justify-between">
            <a class="link link-hover text-sm" href="{{ route('login') }}">{{ team_trans('invitations.register.already_registered') }}</a>

            <x-button.action type="submit">{{ team_trans('invitations.register.submit') }}</x-button.action>
        </div>
    </form>
@endsection
