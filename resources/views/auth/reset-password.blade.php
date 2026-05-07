@extends('layouts.layout')

@section('title', 'Choose a New Password')

@section('body_theme', 'dark')


@section('content')
<div class="ggwp-auth-shell ggwp-auth-shell--focused">
  <section class="ggwp-auth-intro" aria-labelledby="resetPasswordIntroTitle">
    <span class="ggwp-page-eyebrow">Password recovery</span>
    <h1 id="resetPasswordIntroTitle" class="mb-2">Choose a new password</h1>
    <p class="text-secondary mb-0">Use a strong password you have not used elsewhere.</p>
  </section>
  <div class="row justify-content-center">
    <div class="col-md-8 col-lg-5">
      <section class="card app-card" aria-labelledby="resetPasswordFormHeading">
        <div class="card-body">
          <h2 id="resetPasswordFormHeading" class="h4 mb-3">Set password</h2>
          <p class="text-secondary">After the reset succeeds, you will return to your GGWP workspace.</p>

          <form method="POST" action="{{ route('password.update') }}" class="d-grid gap-3" data-validate-form novalidate>
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <input type="hidden" name="email" value="{{ $email }}">

            <div>
              <label class="form-label" for="resetPasswordEmail">Email</label>
              <input
                id="resetPasswordEmail"
                class="form-control @error('email') is-invalid @enderror"
                type="email"
                value="{{ $email }}"
                autocomplete="email"
                readonly
              >
              @error('email')
                <div class="invalid-feedback d-block">{{ $message }}</div>
              @enderror
            </div>

            <div>
              <label class="form-label" for="resetPasswordInput">New Password</label>
              <input
                id="resetPasswordInput"
                class="form-control @error('password') is-invalid @enderror"
                name="password"
                type="password"
                autocomplete="new-password"
                required
              >
              @error('password')
                <div class="invalid-feedback d-block">{{ $message }}</div>
              @enderror
            </div>

            <div>
              <label class="form-label" for="resetPasswordConfirmation">Confirm New Password</label>
              <input
                id="resetPasswordConfirmation"
                class="form-control"
                name="password_confirmation"
                type="password"
                autocomplete="new-password"
                required
              >
            </div>

            <button class="btn btn-danger" type="submit">Reset Password</button>
          </form>
        </div>
      </section>
    </div>
  </div>
</div>
@endsection
