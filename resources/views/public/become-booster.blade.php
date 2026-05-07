@extends('layouts.layout')

@php
  $boosterPage = $pageContent ?? [];
@endphp

@section('title', 'Become a VALORANT Booster')



@section('content')
<div class="ggwp-page-shell ggwp-booster-application-page">
  <div class="row justify-content-center">
    <div class="col-xl-8 col-lg-10">
      @if(session('status'))
        <div class="alert alert-success" role="alert">{{ session('status') }}</div>
      @endif

      @if($errors->has('form'))
        <div class="alert alert-danger" role="alert">{{ $errors->first('form') }}</div>
      @endif

      <section class="card app-card ggwp-panel-card">
        <div class="card-body">
          <header class="ggwp-form-intro">
            <span class="ggwp-page-eyebrow">Application details</span>
            <h1 class="h4 mb-0">{{ data_get($boosterPage, 'header.title', 'Become a VALORANT Booster') }}</h1>
            <p class="text-secondary mb-0">Use accurate rank, region, tracker, and marketplace information so the review team can evaluate fit quickly.</p>
          </header>

          <form action="{{ route('become-booster.submit') }}" method="POST" class="row g-3" data-validate-form novalidate>
            @csrf
            <input type="text" name="website" value="{{ old('website') }}" class="d-none" tabindex="-1" autocomplete="off" aria-hidden="true">

            <div class="col-md-6">
              <label class="form-label" for="boosterApplicantName">Name</label>
              <input id="boosterApplicantName" class="form-control @error('name') is-invalid @enderror" name="name" value="{{ old('name') }}" autocomplete="name" maxlength="120" required>
              @error('name')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            <div class="col-md-6">
              <label class="form-label" for="boosterApplicantNickname">Nickname / Gaming Name</label>
              <input
                id="boosterApplicantNickname"
                class="form-control @error('nickname') is-invalid @enderror"
                name="nickname"
                value="{{ old('nickname') }}"
                maxlength="50"
                pattern="[A-Za-z0-9][A-Za-z0-9 _.\-]*"
                required
              >
              <div class="invalid-feedback">Enter a valid nickname or gaming name.</div>
              @error('nickname')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            <div class="col-md-6">
              <label class="form-label" for="boosterApplicantEmail">Email</label>
              <input id="boosterApplicantEmail" type="email" class="form-control @error('email') is-invalid @enderror" name="email" value="{{ old('email') }}" autocomplete="email" inputmode="email" autocapitalize="off" spellcheck="false" required>
              @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            <div class="col-md-6">
              <label class="form-label" for="boosterApplicantCurrentRank">Current Rank</label>
              <input id="boosterApplicantCurrentRank" class="form-control @error('current_rank') is-invalid @enderror" name="current_rank" value="{{ old('current_rank') }}" required>
              @error('current_rank')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            <div class="col-md-6">
              <label class="form-label" for="boosterApplicantPeakRank">Peak Rank</label>
              <input id="boosterApplicantPeakRank" class="form-control @error('peak_rank') is-invalid @enderror" name="peak_rank" value="{{ old('peak_rank') }}" required>
              @error('peak_rank')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            <div class="col-12">
              <label class="form-label" for="boosterApplicantAverageTime">Average time required to boost from Ascendant 1 to Immortal 1</label>
              <input id="boosterApplicantAverageTime" class="form-control @error('average_time') is-invalid @enderror" name="average_time" value="{{ old('average_time') }}" placeholder="Example: 2 days 6 hours" required>
              @error('average_time')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            <div class="col-md-6">
              <label class="form-label" for="boosterApplicantDiscord">Discord</label>
              <input id="boosterApplicantDiscord" class="form-control @error('discord') is-invalid @enderror" name="discord" value="{{ old('discord') }}" placeholder="username or username#1234" autocomplete="username" autocapitalize="off" spellcheck="false" maxlength="64" required>
              @error('discord')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            <div class="col-md-6">
              <label class="form-label" for="boosterApplicantTracker">Main Account Tracker</label>
              <input id="boosterApplicantTracker" type="url" class="form-control @error('main_account_tracker') is-invalid @enderror" name="main_account_tracker" value="{{ old('main_account_tracker') }}" placeholder="https://tracker.gg/..." inputmode="url" autocapitalize="off" spellcheck="false" required>
              @error('main_account_tracker')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            <div class="col-12">
              <label class="form-label" for="boosterApplicantMarketplace">Profile link to any other marketplace that you've sold boosting services on before</label>
              <input id="boosterApplicantMarketplace" type="url" class="form-control @error('marketplace_profile') is-invalid @enderror" name="marketplace_profile" value="{{ old('marketplace_profile') }}" placeholder="https://..." inputmode="url" autocapitalize="off" spellcheck="false">
              @error('marketplace_profile')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            <fieldset class="col-12">
              <legend class="form-label">Regions you can boost</legend>
              <div class="row g-2">
                @foreach(['EU', 'AP', 'OCE', 'NA', 'MENA', 'LATAM'] as $region)
                  <div class="col-md-4 col-sm-6">
                    <label class="form-check">
                      <input
                        class="form-check-input @error('regions') is-invalid @enderror"
                        type="checkbox"
                        name="regions[]"
                        value="{{ $region }}"
                        {{ in_array($region, old('regions', []), true) ? 'checked' : '' }}
                      >
                      <span class="form-check-label">{{ $region }}</span>
                    </label>
                  </div>
                @endforeach
              </div>
              @error('regions')
                <div class="text-danger small mt-2">{{ $message }}</div>
              @enderror
              @error('website')
                <div class="text-danger small mt-2">{{ $message }}</div>
              @enderror
            </fieldset>

            <div class="col-12 d-flex flex-wrap gap-2 pt-1">
              <button class="btn btn-danger" type="submit">Send Application</button>
              <a class="btn btn-outline-secondary" href="{{ route('home') }}">Cancel</a>
            </div>
          </form>
        </div>
      </section>
    </div>
  </div>
</div>
@endsection
