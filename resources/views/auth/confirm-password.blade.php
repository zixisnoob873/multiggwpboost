@extends('layouts.layout')

@section('title', 'Confirm Password')

@section('body_theme', 'dark')


@section('content')
<div class="ggwp-auth-shell ggwp-auth-shell--focused">
  <section class="ggwp-auth-intro" aria-labelledby="confirmPasswordIntroTitle">
    <span class="ggwp-page-eyebrow">Account security</span>
    <h1 id="confirmPasswordIntroTitle" class="mb-2">Confirm your password</h1>
    <p class="text-secondary mb-0">Confirm your password before changing sensitive account connections.</p>
  </section>
  <div class="row justify-content-center">
    <div class="col-md-8 col-lg-5">
      <section class="card app-card" aria-labelledby="confirmPasswordFormHeading">
        <div class="card-body">
          <h2 id="confirmPasswordFormHeading" class="h4 mb-3">Security check</h2>

          <form method="POST" action="{{ route('password.confirm.submit') }}" class="d-grid gap-3" data-validate-form novalidate>
            @csrf

            <div>
              <label class="form-label" for="confirmPasswordInput">Password</label>
              <input
                id="confirmPasswordInput"
                class="form-control @error('password') is-invalid @enderror"
                name="password"
                type="password"
                autocomplete="current-password"
                @error('password') aria-describedby="confirmPasswordError" @enderror
                required
              >
              @error('password')
                <div id="confirmPasswordError" class="invalid-feedback d-block">{{ $message }}</div>
              @enderror
            </div>

            <button class="btn btn-danger" type="submit">Confirm Password</button>
          </form>
        </div>
      </section>
    </div>
  </div>
</div>
@endsection
