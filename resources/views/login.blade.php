@extends('layouts.layout')

@section('title', 'Login')

@section('body_theme', 'dark')


@php
  $loginCaptcha = $loginCaptcha ?? [];
  $captchaRequired = (bool) ($loginCaptcha['required'] ?? false);
  $captchaChallenge = (string) ($loginCaptcha['challenge'] ?? '');
@endphp

@section('content')
<div class="ggwp-auth-shell ggwp-auth-shell--focused">
    <div class="row justify-content-center">
      <div class="col-md-8 col-lg-5">
        <section class="card app-card" aria-labelledby="loginHeading">
          <div class="card-body">
            <header>
              <span class="ggwp-page-eyebrow">Account access</span>
              <h1 id="loginHeading" class="h4 mb-1">Login</h1>
              <p class="text-secondary small mb-3">Manage orders, chat, and boost progress.</p>
            </header>

            @if(session('status'))
              <div class="alert alert-success" role="status">{{ session('status') }}</div>
            @endif

            @if($errors->has('oauth'))
              <div class="alert alert-danger" role="alert">{{ $errors->first('oauth') }}</div>
            @endif

            <form method="POST" action="{{ route('login.submit', [], false) }}" class="d-grid gap-3" data-validate-form novalidate>
              @csrf

              <div>
                <label class="form-label" for="loginEmail">Email</label>
                <input
                  id="loginEmail"
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
                <label class="form-label" for="loginPassword">Password</label>
                <div class="input-group ggwp-password-toggle-group">
                  <input
                    class="form-control @error('password') is-invalid @enderror"
                    id="loginPassword"
                    name="password"
                    type="password"
                    autocomplete="current-password"
                    required
                  >
                  <button
                    class="btn btn-outline-light ggwp-password-toggle"
                    type="button"
                    data-password-toggle
                    data-target="loginPassword"
                    aria-controls="loginPassword"
                    aria-label="Show password"
                  >
                    Show
                  </button>
                </div>
                @error('password')
                  <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
              </div>

              <div
                id="loginCaptchaGroup"
                class="{{ $captchaRequired ? '' : 'd-none' }}"
                data-login-captcha-group
                data-captcha-required="{{ $captchaRequired ? '1' : '0' }}"
              >
                <label class="form-label" for="loginCaptchaInput">7-digit captcha</label>
                <div class="card border-secondary-subtle bg-body-tertiary mb-2">
                  <div class="card-body py-2">
                    <div class="small text-secondary mb-1">Enter the numeric code exactly as shown.</div>
                    <div class="h4 mb-0 font-monospace" data-login-captcha-challenge>{{ $captchaChallenge }}</div>
                  </div>
                </div>
                <input
                  class="form-control @error('captcha') is-invalid @enderror"
                  id="loginCaptchaInput"
                  name="captcha"
                  type="text"
                  inputmode="numeric"
                  pattern="[0-9]{7}"
                  maxlength="7"
                  autocomplete="off"
                  value="{{ old('captcha') }}"
                  {{ $captchaRequired ? 'required' : '' }}
                >
                <div class="invalid-feedback" id="loginCaptchaFeedback">Enter the 7-digit captcha exactly as shown.</div>
                @error('captcha')
                  <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
              </div>

              <div class="d-flex justify-content-between align-items-center">
                <label class="form-check m-0">
                  <input class="form-check-input" type="checkbox" name="remember">
                  <span class="form-check-label">Remember me</span>
                </label>
                <a class="text-secondary" href="{{ route('password.request') }}">Forgot password?</a>
              </div>
              <button class="btn btn-danger" type="submit">Login</button>
            </form>

            <div class="d-grid gap-2 mt-2">
              <a class="btn btn-outline-light ggwp-social-auth-btn" href="{{ route('oauth.redirect', ['provider' => 'google']) }}">
                <img src="{{ asset('assets/google.png') }}" alt="" class="ggwp-social-auth-icon" aria-hidden="true" loading="lazy" decoding="async">
                <span>Continue with Google</span>
              </a>
              <a class="btn btn-outline-light ggwp-social-auth-btn" href="{{ route('oauth.redirect', ['provider' => 'discord']) }}">
                <img src="{{ asset('assets/discord.png') }}" alt="" class="ggwp-social-auth-icon" aria-hidden="true" loading="lazy" decoding="async">
                <span>Continue with Discord</span>
              </a>
            </div>
            <p class="text-secondary mb-0 mt-2">No account? <a href="{{ route('signup') }}">Create one</a></p>
          </div>
        </section>
      </div>
    </div>
  </div>
@endsection

@push('scripts')
<script nonce="{{ $cspNonce ?? '' }}">
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-password-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
      const targetId = button.getAttribute('data-target');
      const input = targetId ? document.getElementById(targetId) : null;

      if (!(input instanceof HTMLInputElement)) {
        return;
      }

      const reveal = input.type === 'password';
      input.type = reveal ? 'text' : 'password';
      button.textContent = reveal ? 'Hide' : 'Show';
      button.setAttribute('aria-label', reveal ? 'Hide password' : 'Show password');
    });
  });

  const form = document.querySelector('form[action="{{ route('login.submit', [], false) }}"]');
  const captchaGroup = document.querySelector('[data-login-captcha-group]');
  const captchaInput = document.getElementById('loginCaptchaInput');
  const captchaFeedback = document.getElementById('loginCaptchaFeedback');
  const captchaRequired = captchaGroup?.getAttribute('data-captcha-required') === '1';

  if (!(captchaInput instanceof HTMLInputElement)) {
    return;
  }

  const showCaptchaError = (message) => {
    captchaInput.classList.add('is-invalid');

    if (captchaFeedback) {
      captchaFeedback.textContent = message;
      captchaFeedback.classList.add('d-block');
    }
  };

  const clearCaptchaError = () => {
    captchaInput.classList.remove('is-invalid');

    if (captchaFeedback) {
      captchaFeedback.textContent = 'Enter the 7-digit captcha exactly as shown.';
      captchaFeedback.classList.remove('d-block');
    }
  };

  captchaInput.addEventListener('input', () => {
    captchaInput.value = captchaInput.value.replace(/\D+/g, '').slice(0, 7);

    if (captchaInput.classList.contains('is-invalid')) {
      clearCaptchaError();
    }
  });

  form?.addEventListener('submit', (event) => {
    if (!captchaRequired) {
      return;
    }

    const value = (captchaInput.value || '').trim();

    if (value === '') {
      event.preventDefault();
      showCaptchaError('Enter the 7-digit captcha to continue.');
      captchaInput.focus();

      return;
    }

    if (!/^\d{7}$/.test(value)) {
      event.preventDefault();
      showCaptchaError('The captcha must be exactly 7 digits.');
      captchaInput.focus();
    }
  });
});
</script>
@endpush
