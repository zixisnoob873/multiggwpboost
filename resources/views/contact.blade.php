@extends('layouts.layout')

@php
  $contactPage = $pageContent ?? [];
  $contactDiscordUrl = (string) data_get($contactPage, 'notice.link_url', 'https://discord.gg/2FD3qq9U');
@endphp

@section('title', 'VALORANT Boosting Support')

@section('content')
<div class="ggwp-page-shell ggwp-contact-shell">
  <h1 class="visually-hidden">{{ data_get($contactPage, 'form.title', 'Contact VALORANT Boosting Support') }}</h1>

  <div class="row g-3 align-items-start">

    <div class="col-12">
      <p id="contact-discord-info" class="ggwp-center-copy">
        {{ data_get($contactPage, 'notice.text', 'We usually respond in 6-12 hours.') }}<br>Join our <a href="{{ $contactDiscordUrl }}" class="ggwp-discord-link">Discord</a> {{ data_get($contactPage, 'notice.suffix', 'server for faster support.') }}
      </p>
    </div>

    <div class="col-lg-5">
      <section class="app-card ggwp-contact-info" aria-labelledby="contactInfoHeading">

        <h2 id="contactInfoHeading" class="ggwp-info-title mb-3">{{ data_get($contactPage, 'info.title', 'Need VALORANT Boosting Help?') }}</h2>

        @foreach(data_get($contactPage, 'info.items', []) as $item)
          <article class="ggwp-info-section">
            <h3 class="ggwp-info-heading">{{ data_get($item, 'title') }}</h3>
            <p class="text-secondary mb-0">
              {{ data_get($item, 'body') }}
            </p>
          </article>
        @endforeach

      </section>
    </div>

    <div class="col-lg-6 offset-lg-1">
      <section class="app-card ggwp-contact-form-card" aria-labelledby="contactFormHeading">

        <h2 id="contactFormHeading" class="ggwp-contact-title mb-3">Send a message</h2>
        <p class="text-secondary mb-3">
          {{ data_get($contactPage, 'form.description', 'Send your question and we\'ll help with your order, quote, Duo / Self-Play setup, or custom VALORANT boost request.') }}
        </p>

        @if(session('status'))
          <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        @if($errors->any())
          <div class="alert alert-danger">
            {{ $errors->first('form') ?: 'Please correct the highlighted fields and try again.' }}
          </div>
        @endif

        <form id="contactForm" method="POST" action="{{ route('contact.submit') }}" data-validate-form data-loading-form data-contact-form novalidate>
          @csrf
          <input type="text" name="website" value="{{ old('website') }}" class="d-none" tabindex="-1" autocomplete="off" aria-hidden="true">

          <div class="mb-3">
            <label class="form-label fw-semibold" for="contactName">Name *</label>
            <input type="text" class="form-control @error('name') is-invalid @enderror" id="contactName" name="name" value="{{ old('name', auth()->user()?->name) }}" autocomplete="name" maxlength="120" required>
            <div class="invalid-feedback">Please enter your name.</div>
            @error('name')
              <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
          </div>

          <div class="mb-3">
            <label class="form-label fw-semibold" for="contactEmail">Email *</label>
            <input type="email" class="form-control @error('email') is-invalid @enderror" id="contactEmail" name="email" value="{{ old('email', auth()->user()?->email) }}" autocomplete="email" inputmode="email" autocapitalize="off" spellcheck="false" maxlength="255" required>
            <div class="invalid-feedback">Please enter a valid email address.</div>
            @error('email')
              <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
          </div>

          <div class="mb-3">
            <label class="form-label fw-semibold" for="contactOrderRef">Order ID (Optional)</label>
            <input type="text" class="form-control @error('order_reference') is-invalid @enderror" id="contactOrderRef" name="order_reference" value="{{ old('order_reference') }}" autocapitalize="characters" spellcheck="false" maxlength="80">
            @error('order_reference')
              <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
          </div>

          <div class="mb-3">
            <label class="form-label fw-semibold" for="contactMessage">Description *</label>
            <textarea class="form-control @error('message') is-invalid @enderror" id="contactMessage" name="message" rows="5" minlength="20" maxlength="600" aria-describedby="contactMessageHint contactMessageCount" data-character-count-input required>{{ old('message') }}</textarea>
            <div class="d-flex justify-content-end align-items-center mt-2">
              <div id="contactMessageHint" class="visually-hidden">Use 20 to 600 characters.</div>
              <div id="contactMessageCount" class="ggwp-character-count" data-character-count-output aria-live="polite">0 / 600</div>
            </div>
            <div class="invalid-feedback">Description must be between 20 and 600 characters.</div>
            @error('message')
              <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
          </div>

          @error('website')
            <div class="invalid-feedback d-block">{{ $message }}</div>
          @enderror

          <div class="d-grid">
            <button type="submit" class="btn ggwp-submit-btn" data-busy-label="Sending...">
              Send Message
            </button>
          </div>

        </form>

      </section>
    </div>

  </div>

</div>
@endsection
