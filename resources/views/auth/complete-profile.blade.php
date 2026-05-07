@extends('layouts.layout')

@section('title', 'Complete Profile')

@section('body_theme', 'dark')

@php
  $providerLabel = (string) ($profile['provider_label'] ?? 'OAuth');
  $emailLocked = (bool) ($emailLocked ?? false);
  $emailHelpId = $emailLocked ? 'oauthEmailHelp' : null;
@endphp

@section('content')
<div class="ggwp-auth-shell ggwp-auth-shell--focused">
    <div class="row justify-content-center">
      <div class="col-md-9 col-lg-6">
        <section class="card app-card" aria-labelledby="completeProfileHeading">
          <div class="card-body">
            <header>
              <span class="ggwp-page-eyebrow">{{ $providerLabel }} signup</span>
              <h1 id="completeProfileHeading" class="h4 mb-1">Complete profile</h1>
              <p class="text-secondary small mb-3">Confirm the details we need to create your customer account.</p>
            </header>

            @if($errors->has('oauth'))
              <div class="alert alert-danger" role="alert">{{ $errors->first('oauth') }}</div>
            @endif

            <form method="POST" action="{{ route('oauth.complete-profile.submit') }}" class="d-grid gap-3" data-validate-form novalidate>
              @csrf

              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label" for="oauthFirstName">First name</label>
                  <input
                    id="oauthFirstName"
                    class="form-control @error('first_name') is-invalid @enderror"
                    name="first_name"
                    type="text"
                    autocomplete="given-name"
                    value="{{ old('first_name', $profile['first_name'] ?? '') }}"
                    required
                  >
                  @error('first_name')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                  @enderror
                </div>
                <div class="col-md-6">
                  <label class="form-label" for="oauthLastName">Last name</label>
                  <input
                    id="oauthLastName"
                    class="form-control @error('last_name') is-invalid @enderror"
                    name="last_name"
                    type="text"
                    autocomplete="family-name"
                    value="{{ old('last_name', $profile['last_name'] ?? '') }}"
                    required
                  >
                  @error('last_name')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                  @enderror
                </div>
              </div>

              <div>
                <label class="form-label" for="oauthNickname">Nickname</label>
                <input
                  id="oauthNickname"
                  class="form-control @error('nickname') is-invalid @enderror"
                  name="nickname"
                  type="text"
                  autocomplete="nickname"
                  maxlength="25"
                  pattern="[A-Za-z0-9]+"
                  value="{{ old('nickname', ($profile['nickname'] ?? '') !== '' ? $profile['nickname'] : ($profile['suggested_nickname'] ?? '')) }}"
                  data-nickname-input
                  required
                >
                <div class="form-text">Letters and numbers only, with no spaces or symbols.</div>
                <div class="invalid-feedback" data-nickname-feedback>Use only letters and numbers, with no spaces or symbols.</div>
                @error('nickname')
                  <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
              </div>

              <div>
                <label class="form-label" for="oauthEmail">Email</label>
                <input
                  id="oauthEmail"
                  class="form-control @error('email') is-invalid @enderror"
                  name="email"
                  type="email"
                  placeholder="you@example.com"
                  autocomplete="email"
                  inputmode="email"
                  autocapitalize="off"
                  spellcheck="false"
                  value="{{ old('email', $profile['email'] ?? '') }}"
                  @if($emailLocked) readonly aria-describedby="{{ $emailHelpId }}" @endif
                  required
                >
                @if($emailLocked)
                  <div class="form-text" id="{{ $emailHelpId }}">{{ $providerLabel }} provided this email address.</div>
                @endif
                @error('email')
                  <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
              </div>

              <button class="btn btn-danger" type="submit">Finish Signup</button>
            </form>

            <p class="text-secondary mb-0 mt-2">Already have an account? <a href="{{ route('login') }}">Login</a></p>
          </div>
        </section>
      </div>
    </div>
  </div>
@endsection
