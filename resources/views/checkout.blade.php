@extends('layouts.layout')

@php
  $checkoutGame = $activeGame ?? $ggwpGame ?? [];
  $checkoutGameShortName = $checkoutGame['shortName'] ?? 'VALORANT';
@endphp

@section('title', $checkoutGameShortName.' Boost Pricing')



@section('content')
<div class="ggwp-page-shell ggwp-page-shell--wide ggwp-checkout-page">
  <header class="ggwp-checkout-hero">
    <div>
      <span class="ggwp-page-eyebrow">Secure checkout</span>
      <h1 class="mb-2">Secure {{ $checkoutGameShortName }} Boost Checkout</h1>
      <p class="text-secondary mb-0">Confirm contact details, payment method, policy acknowledgements, and the live quote generated from your boost setup.</p>
    </div>
    <ol class="ggwp-checkout-steps" aria-label="Checkout steps">
      <li>Review order</li>
      <li>Confirm contact</li>
      <li>Pay securely</li>
    </ol>
  </header>

  <div class="row g-3 align-items-start">
    <section class="col-lg-7">
      <div class="card app-card ggwp-panel-card ggwp-checkout-card">
        <div class="card-body">
          <h2 class="h4 mb-2">Payment details</h2>
          <p class="text-secondary mb-3">The order summary stays visible while you finish checkout.</p>

          @if($errors->any())
            <div class="alert alert-danger" role="alert">
              <div class="fw-semibold mb-1">Please fix the checkout details below.</div>
              <ul class="mb-0 ps-3">
                @foreach($errors->all() as $error)
                  <li>{{ $error }}</li>
                @endforeach
              </ul>
            </div>
          @endif

          @if($checkoutBlockedForBooster ?? false)
            <div class="alert alert-warning mb-0" role="alert">
              <div class="fw-semibold mb-1">Booster accounts cannot buy services.</div>
              <p class="mb-3">Your account is approved for booster operations only. To place a customer order, sign in with a customer account instead.</p>
              <div class="d-flex flex-wrap gap-2">
                <a class="btn btn-danger" href="{{ route('booster-dashboard') }}">Open Booster Dashboard</a>
                <a class="btn btn-outline-light" href="{{ route('home') }}">Return to Home</a>
              </div>
            </div>
          @else
          <form id="checkoutForm" class="d-grid gap-3" action="{{ route('checkout.submit') }}" method="POST" novalidate>
            @csrf
            <input type="hidden" id="orderPayload" name="orderPayload">

            <section aria-labelledby="checkoutContactHeading">
              <h3 id="checkoutContactHeading" class="h5 mb-3">Contact information</h3>
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label" for="firstName">First name</label>
                  <input class="form-control @error('firstName') is-invalid @enderror" id="firstName" name="firstName" type="text" autocomplete="given-name" value="{{ old('firstName') }}" required>
                  @error('firstName')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                  @enderror
                </div>
                <div class="col-md-6">
                  <label class="form-label" for="lastName">Last name</label>
                  <input class="form-control @error('lastName') is-invalid @enderror" id="lastName" name="lastName" type="text" autocomplete="family-name" value="{{ old('lastName') }}" required>
                  @error('lastName')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                  @enderror
                </div>
                <div class="col-md-6">
                  <label class="form-label" for="email">Email</label>
                  <input class="form-control @error('email') is-invalid @enderror" id="email" name="email" type="email" placeholder="you@example.com" autocomplete="email" inputmode="email" autocapitalize="off" spellcheck="false" value="{{ old('email') }}" required>
                  @error('email')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                  @enderror
                </div>
                <div class="col-md-6">
                  <label class="form-label" for="contactMethod">Preferred contact method</label>
                  <select id="contactMethod" name="contactMethod" class="form-select @error('contactMethod') is-invalid @enderror" required>
                    <option value="email" @selected(old('contactMethod', 'email') === 'email')>Email</option>
                    <option value="whatsapp" @selected(old('contactMethod') === 'whatsapp')>WhatsApp</option>
                    <option value="discord" data-discord-option @selected(old('contactMethod') === 'discord')>Discord</option>
                  </select>
                  <div class="invalid-feedback">Please choose a contact method.</div>
                  @error('contactMethod')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                  @enderror
                </div>

                <div class="col-md-6 d-none" id="whatsappWrap">
                  <label class="form-label" for="whatsapp">WhatsApp number</label>
                  <input class="form-control @error('whatsapp') is-invalid @enderror" id="whatsapp" name="whatsapp" type="tel" placeholder="+92 300 1234567" autocomplete="tel" inputmode="tel" value="{{ old('whatsapp') }}">
                  <div class="invalid-feedback">Please enter a valid WhatsApp number.</div>
                  @error('whatsapp')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                  @enderror
                </div>

                <div class="col-md-6 d-none" id="discordWrap">
                  <label class="form-label" for="discord">Discord username</label>
                  <input class="form-control @error('discord') is-invalid @enderror" id="discord" name="discord" type="text" placeholder="username or username#1234" autocomplete="username" autocapitalize="off" spellcheck="false" value="{{ old('discord') }}">
                  <div class="invalid-feedback">Please enter your Discord username.</div>
                  @error('discord')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                  @enderror
                </div>
              </div>
            </section>

            <section aria-labelledby="checkoutPaymentHeading">
              <h3 id="checkoutPaymentHeading" class="h5 mb-3">Payment method</h3>
              <div class="mt-3 d-flex flex-column gap-2">
                @foreach(($paymentProviders ?? []) as $provider)
                  @php
                    $providerEnabled = (bool) ($provider['isAvailable'] ?? true);
                    $providerConfigured = (bool) ($provider['isConfigured'] ?? true);
                    $providerReady = $providerEnabled && $providerConfigured;
                    $badgeClass = ! $providerEnabled
                      ? 'text-bg-secondary'
                      : ($providerConfigured ? 'text-bg-success' : 'text-bg-warning');
                    $badgeLabel = ! $providerEnabled
                      ? 'Disabled'
                      : ($providerConfigured ? 'Ready' : 'Setup needed');
                  @endphp
                  <article class="card app-card">
                    <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-2">
                      <div>
                        <label class="form-check mb-0">
                          <input
                            class="form-check-input payment-provider-option"
                            type="radio"
                            name="paymentMethod"
                            id="payment-{{ $provider['key'] }}"
                            value="{{ $provider['key'] }}"
                            data-notice="{{ $provider['notice'] }}"
                            data-submit-label="{{ $provider['submitLabel'] }}"
                            data-provider-ready="{{ $providerReady ? '1' : '0' }}"
                            @checked(old('paymentMethod', $defaultPaymentProvider['key'] ?? null) === ($provider['key'] ?? null))
                            @disabled(! $providerReady)
                          >
                          <span class="form-check-label">
                            <span class="fw-semibold d-block">{{ $provider['label'] }}</span>
                            <span class="text-secondary small">{{ $provider['description'] }}</span>
                          </span>
                        </label>
                      </div>
                      <span class="badge {{ $badgeClass }}">
                        {{ $badgeLabel }}
                      </span>
                    </div>
                  </article>
                @endforeach
              </div>
              @error('paymentMethod')
                <div class="invalid-feedback d-block mt-2">{{ $message }}</div>
              @enderror
              @error('payment')
                <div class="invalid-feedback d-block mt-2">{{ $message }}</div>
              @enderror
              <div id="paymentMethodNotice" class="alert alert-info small mb-0 mt-2">
                {{ $defaultPaymentProvider['notice'] ?? 'Select a payment provider to continue.' }}
              </div>
            </section>

            <section class="row g-2 align-items-end" aria-label="Promo code">
              <div class="col-md-8">
                <label class="form-label" for="promoCode">Promo code</label>
                <input
                  id="promoCode"
                  name="promoCode"
                  class="form-control @error('promoCode') is-invalid @enderror"
                  value="{{ old('promoCode') }}"
                  autocomplete="off"
                  autocapitalize="characters"
                  spellcheck="false"
                >
                @error('promoCode')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>
              <div class="col-md-4">
                <div class="d-grid gap-2">
                  <button id="applyPromoCodeBtn" class="btn btn-outline-light" type="button">Apply code</button>
                  <button id="removePromoCodeBtn" class="btn btn-outline-secondary d-none" type="button">Remove code</button>
                </div>
              </div>
              <div class="col-12">
                <div id="promoCodeFeedback" class="alert d-none small mb-0 py-2"></div>
              </div>
            </section>

            <fieldset class="d-grid gap-2">
              <legend class="visually-hidden">Checkout acknowledgements</legend>
              <label class="form-check">
                <input class="form-check-input @error('policy') is-invalid @enderror" id="policy" name="policy" type="checkbox" value="1" @checked(old('policy')) required>
                <span class="form-check-label">I agree to reschedule and cancellation policy</span>
              </label>
              @error('policy')
                <div class="invalid-feedback d-block">{{ $message }}</div>
              @enderror

              <label class="form-check">
                <input class="form-check-input @error('compliance') is-invalid @enderror" id="compliance" name="compliance" type="checkbox" value="1" @checked(old('compliance')) required>
                <span class="form-check-label">I understand account-sharing and Duo / Self-Play boosting risks</span>
              </label>
              @error('compliance')
                <div class="invalid-feedback d-block">{{ $message }}</div>
              @enderror

              <div id="checkoutAcknowledgementError" class="invalid-feedback d-none">Please confirm both policy acknowledgements before payment.</div>
            </fieldset>

            <div class="d-grid">
              <button id="payBtn" class="btn btn-danger btn-lg" type="submit" disabled>{{ $defaultPaymentProvider['submitLabel'] ?? 'Continue to Payment' }}</button>
            </div>

            <div id="paymentStatus" class="alert alert-success d-none"></div>
          </form>
          @endif
        </div>
      </div>
    </section>

    <aside class="col-lg-5 ggwp-checkout-sidebar">
      <div class="ggwp-checkout-sidebar-stack">
        <div class="card app-card ggwp-panel-card ggwp-checkout-summary">
          <div class="card-body">
            <span class="ggwp-page-eyebrow">Order summary</span>
            <h2 class="h4 mb-3">{{ $checkoutGameShortName }} boost summary</h2>

            <dl id="orderSummaryDetails" class="d-grid gap-2 small">
              <div><dt class="text-secondary d-block">Order type</dt><dd class="mb-0"><strong id="osOrderType">-</strong></dd></div>
              <div><dt class="text-secondary d-block">Current division</dt><dd class="mb-0"><strong id="osCurrentDivision">-</strong></dd></div>
              <div><dt class="text-secondary d-block">Desired division</dt><dd class="mb-0"><strong id="osDesiredDivision">-</strong></dd></div>
              <div><dt class="text-secondary d-block">Current RR</dt><dd class="mb-0"><strong id="osCurrentRR">-</strong></dd></div>
              <div><dt class="text-secondary d-block">Average RR / service detail</dt><dd class="mb-0"><strong id="osAvgRR">-</strong></dd></div>
              <div><dt class="text-secondary d-block">Boost mode</dt><dd class="mb-0"><strong id="osPlayType">-</strong></dd></div>
              <div>
                <dt class="text-secondary d-block">Add-ons</dt>
                <dd class="mb-0">
                  <ul id="osAddons" class="mb-0 ps-3">
                    <li class="text-secondary">None</li>
                  </ul>
                </dd>
              </div>
              <div id="osSpecificAgentsRow" class="d-none">
                <dt class="text-secondary d-block">Specific Agents</dt>
                <dd class="mb-0">
                  <ul id="osSpecificAgents" class="mb-0 ps-3">
                    <li class="text-secondary">None</li>
                  </ul>
                </dd>
              </div>
              <div id="osOneTrickAgentRow" class="d-none">
                <dt class="text-secondary d-block">One-Trick Agent</dt>
                <dd class="mb-0">
                  <ul id="osOneTrickAgent" class="mb-0 ps-3">
                    <li class="text-secondary">None</li>
                  </ul>
                </dd>
              </div>
            </dl>

            <a class="btn btn-outline-light btn-sm mt-2" href="{{ (($checkoutGame['slug'] ?? 'valorant') === 'valorant') ? route('home') : route('games.show', ['game' => $checkoutGame['slug']]) }}">Return to {{ $checkoutGameShortName }} boost setup</a>

            <hr>

            <dl class="d-grid gap-1">
              <div class="d-flex justify-content-between"><dt class="text-secondary fw-normal">Base price</dt><dd class="mb-0"><strong id="osBase">$0.00</strong></dd></div>
              <div class="d-flex justify-content-between"><dt class="text-secondary fw-normal">Add-ons</dt><dd class="mb-0"><strong id="osAddonsPrice">$0.00</strong></dd></div>
              <div id="osPromoCodeRow" class="d-flex justify-content-between d-none"><dt class="text-secondary fw-normal">Promo code</dt><dd class="mb-0"><strong id="osPromoCode">None</strong></dd></div>
              <div id="osOriginalTotalRow" class="d-flex justify-content-between d-none"><dt class="text-secondary fw-normal">Original total</dt><dd class="mb-0"><strong id="osOriginalTotal">$0.00</strong></dd></div>
              <div id="osPromoDiscountRow" class="d-flex justify-content-between d-none"><dt class="text-secondary fw-normal">Promo discount</dt><dd class="mb-0"><strong id="osPromoDiscount">-$0.00</strong></dd></div>
              <div class="d-flex justify-content-between mt-1"><dt class="text-secondary fw-normal">Total</dt><dd class="mb-0"><strong id="osTotal">$0.00</strong></dd></div>
            </dl>
          </div>
        </div>

        <section class="card app-card ggwp-panel-card ggwp-checkout-assurance" aria-labelledby="checkoutRefundHeading">
          <div class="card-body">
            <h2 id="checkoutRefundHeading" class="h4 mb-3">Refund coverage for unfinished {{ $checkoutGameShortName }} boosts</h2>
            <p class="mb-2">Your order is reviewed against the exact service scope you purchased. If we cannot start or complete the {{ $checkoutGameShortName }} boost as agreed, the unfinished portion remains eligible for refund review.</p>
            <p class="mb-0">If anything feels off, contact support with your order number and we will review progress, remaining work, and the best resolution path.</p>
          </div>
        </section>
      </div>
    </aside>
  </div>
</div>
@endsection

@push('scripts')
<script nonce="{{ $cspNonce ?? '' }}">
(() => {
  const user = @json(optional(Auth::user())->only(['first_name', 'last_name', 'email']));
  if (!user || !user.email) {
    return;
  }

  const fillIfEmpty = (id, value) => {
    const el = document.getElementById(id);
    if (!el || el.value.trim()) return;
    el.value = value;
  };

  fillIfEmpty('firstName', user.first_name || '');
  fillIfEmpty('lastName', user.last_name || '');
  fillIfEmpty('email', user.email || '');
})();

(() => {
  const providerInputs = Array.from(document.querySelectorAll('.payment-provider-option'));
  const notice = document.getElementById('paymentMethodNotice');
  const payBtn = document.getElementById('payBtn');

  const syncProviderUi = () => {
    const selected = providerInputs.find((input) => input.checked && !input.disabled);

    if (!selected) {
      if (notice) {
        notice.textContent = 'Payments are unavailable right now. Please contact support.';
      }

      if (payBtn) {
        payBtn.textContent = 'Payment Unavailable';
      }

      return;
    }

    if (notice) {
      notice.textContent = selected.dataset.notice || 'Select a payment provider to continue.';
    }

    if (payBtn) {
      payBtn.textContent = selected.dataset.submitLabel || 'Continue to Payment';
    }
  };

  providerInputs.forEach((input) => input.addEventListener('change', syncProviderUi));
  syncProviderUi();
})();
</script>
@endpush
