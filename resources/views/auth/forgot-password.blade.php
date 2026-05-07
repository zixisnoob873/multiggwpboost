@extends('layouts.layout')

@section('title', 'Reset Password')

@section('body_theme', 'dark')


@section('content')
<div class="ggwp-auth-shell ggwp-auth-shell--focused">
  <section class="ggwp-auth-intro" aria-labelledby="forgotPasswordIntroTitle">
    <span class="ggwp-page-eyebrow">Password recovery</span>
    <h1 id="forgotPasswordIntroTitle" class="mb-2">Reset your password</h1>
    <p class="text-secondary mb-0">Request a secure link and get back to your order workspace.</p>
  </section>
  <div class="row justify-content-center">
    <div class="col-md-8 col-lg-5">
      <section class="card app-card" aria-labelledby="forgotPasswordFormHeading">
        <div class="card-body">
          <h2 id="forgotPasswordFormHeading" class="h4 mb-3">Email reset link</h2>
          <p class="text-secondary">Enter your email address and we&apos;ll send you a secure password reset link if an account exists.</p>

          @if(session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
          @endif

          <form method="POST" action="{{ route('password.email') }}" class="d-grid gap-3" data-validate-form novalidate>
            @csrf

            <div>
              <label class="form-label" for="forgotPasswordEmail">Email</label>
              <input
                id="forgotPasswordEmail"
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

            <button class="btn btn-danger" type="submit">Email Reset Link</button>
          </form>

          <p class="text-secondary mb-0 mt-3">Remembered your password? <a href="{{ route('login') }}">Back to login</a></p>
        </div>
      </section>
    </div>
  </div>
</div>
@endsection
