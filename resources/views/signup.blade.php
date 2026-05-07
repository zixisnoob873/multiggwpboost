@extends('layouts.layout')

@section('title', 'Create Account')

@section('body_theme', 'dark')


@section('content')
<div class="ggwp-auth-shell ggwp-auth-shell--focused">
    <div class="row justify-content-center">
      <div class="col-md-9 col-lg-6">
        <section class="card app-card" aria-labelledby="signupHeading">
          <div class="card-body">
            <header>
              <span class="ggwp-page-eyebrow">Customer account</span>
              <h1 id="signupHeading" class="h4 mb-1">Create account</h1>
              <p class="text-secondary small mb-3">Save details and keep boost conversations tied to your profile.</p>
            </header>

            @if(session('status'))
              <div class="alert alert-success" role="status">{{ session('status') }}</div>
            @endif

            @if($errors->has('oauth'))
              <div class="alert alert-danger" role="alert">{{ $errors->first('oauth') }}</div>
            @endif

            <form method="POST" action="{{ route('signup.submit') }}" class="d-grid gap-3" data-validate-form novalidate>
              @csrf

              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label" for="signupFirstName">First name</label>
                  <input
                    id="signupFirstName"
                    class="form-control @error('first_name') is-invalid @enderror"
                    name="first_name"
                    type="text"
                    autocomplete="given-name"
                    value="{{ old('first_name') }}"
                    required
                  >
                  @error('first_name')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                  @enderror
                </div>
                <div class="col-md-6">
                  <label class="form-label" for="signupLastName">Last name</label>
                  <input
                    id="signupLastName"
                    class="form-control @error('last_name') is-invalid @enderror"
                    name="last_name"
                    type="text"
                    autocomplete="family-name"
                    value="{{ old('last_name') }}"
                    required
                  >
                  @error('last_name')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                  @enderror
                </div>
              </div>

              <div>
                <label class="form-label" for="signupNickname">Nickname</label>
                <input
                  id="signupNickname"
                  class="form-control @error('nickname') is-invalid @enderror"
                  name="nickname"
                  type="text"
                  autocomplete="nickname"
                  maxlength="25"
                  pattern="[A-Za-z0-9]+"
                  value="{{ old('nickname') }}"
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
                <label class="form-label" for="signupEmail">Email</label>
                <input
                  id="signupEmail"
                  class="form-control @error('email') is-invalid @enderror"
                  name="email"
                  type="email"
                  placeholder="you@example.com"
                  autocomplete="email"
                  inputmode="email"
                  autocapitalize="off"
                  spellcheck="false"
                  value="{{ old('email') }}"
                  required
                >
                @error('email')
                  <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
              </div>

              <div>
                <label class="form-label" for="signupPassword">Password</label>
                <input
                  id="signupPassword"
                  class="form-control @error('password') is-invalid @enderror"
                  name="password"
                  type="password"
                  autocomplete="new-password"
                  required
                >
                <div class="form-text">Minimum 8 characters.</div>
              </div>

              <div>
                <label class="form-label" for="signupPasswordConfirmation">Confirm password</label>
                <input
                  id="signupPasswordConfirmation"
                  class="form-control"
                  name="password_confirmation"
                  type="password"
                  autocomplete="new-password"
                  required
                >
                @error('password')
                  <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
              </div>

              <label class="form-check">
                <input
                  class="form-check-input @error('accepted_terms') is-invalid @enderror"
                  name="accepted_terms"
                  type="checkbox"
                  value="1"
                  @checked(old('accepted_terms'))
                  required
                >
                <span class="form-check-label">I agree to Terms, Privacy, and Refund Policy</span>
              </label>
              @error('accepted_terms')
                <div class="invalid-feedback d-block">{{ $message }}</div>
              @enderror

              <button class="btn btn-danger" type="submit">Create Account</button>
            </form>

            <div class="d-grid gap-2 mt-2">
              <a class="btn btn-outline-light ggwp-social-auth-btn" href="{{ route('oauth.redirect', ['provider' => 'google']) }}">
                <img src="{{ asset('assets/google.png') }}" alt="" class="ggwp-social-auth-icon" aria-hidden="true" loading="lazy" decoding="async">
                <span>Sign up with Google</span>
              </a>
              <a class="btn btn-outline-light ggwp-social-auth-btn" href="{{ route('oauth.redirect', ['provider' => 'discord']) }}">
                <img src="{{ asset('assets/discord.png') }}" alt="" class="ggwp-social-auth-icon" aria-hidden="true" loading="lazy" decoding="async">
                <span>Sign up with Discord</span>
              </a>
            </div>
            <p class="text-secondary mb-0 mt-2">Already have an account? <a href="{{ route('login') }}">Login</a></p>
          </div>
        </section>
      </div>
    </div>
  </div>
@endsection
