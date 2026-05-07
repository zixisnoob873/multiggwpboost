@php
  $checkoutBlockedForBooster = \App\Models\User::normalizeRole(request()->user()?->role) === \App\Models\User::ROLE_BOOSTER;
  $summaryCurrentInputId = $summaryCurrentInputId ?? '';
  $summaryTargetInputId = $summaryTargetInputId ?? '';
  $summaryTargetText = $summaryTargetText ?? '';
  $summaryTargetSuffix = $summaryTargetSuffix ?? '';
  $summaryRegionInputId = $summaryRegionInputId ?? '';
  $summaryPlatformInputId = $summaryPlatformInputId ?? '';
  $summaryModeInputId = $summaryModeInputId ?? '';
  $summaryModeText = $summaryModeText ?? '';
  $summaryCurrentFallback = $summaryCurrentFallback ?? 'Current rank';
  $summaryTargetFallback = $summaryTargetFallback ?? 'Target rank';
  $summaryRegionFallback = $summaryRegionFallback ?? 'Region';
  $summaryPlatformFallback = $summaryPlatformFallback ?? 'Platform';
  $summaryModeFallback = $summaryModeFallback ?? 'Queue';
  $deliveryHelperId = "{$totalPriceId}DeliveryHelp";
@endphp

<aside
  class="card app-card ggwp-quote-card"
  aria-labelledby="{{ $totalPriceId }}Heading"
  data-quote-card
  data-quote-current-input="{{ $summaryCurrentInputId }}"
  data-quote-current-fallback="{{ $summaryCurrentFallback }}"
  data-quote-target-input="{{ $summaryTargetInputId }}"
  data-quote-target-text="{{ $summaryTargetText }}"
  data-quote-target-suffix="{{ $summaryTargetSuffix }}"
  data-quote-target-fallback="{{ $summaryTargetFallback }}"
  data-quote-region-input="{{ $summaryRegionInputId }}"
  data-quote-region-fallback="{{ $summaryRegionFallback }}"
  data-quote-platform-input="{{ $summaryPlatformInputId }}"
  data-quote-platform-fallback="{{ $summaryPlatformFallback }}"
  data-quote-mode-input="{{ $summaryModeInputId }}"
  data-quote-mode-text="{{ $summaryModeText }}"
  data-quote-mode-fallback="{{ $summaryModeFallback }}"
>
  <div class="card-body p-4 p-xl-5">
    <div class="ggwp-quote-card__header">
      <div class="ggwp-quote-card__eyebrow-row">
        <span class="ggwp-home-section-kicker">Instant Quote</span>
      </div>
      <h3 id="{{ $totalPriceId }}Heading" class="h4 mb-0">{{ $heading }}</h3>
      <p class="text-secondary mb-0">Review the essentials before checkout. Pricing updates as selections change.</p>
    </div>

    <ul class="ggwp-quote-card__trust" aria-label="Service trust signals">
      <li>Secure checkout</li>
      <li>No payment yet</li>
      <li>Verified boosters</li>
    </ul>

    <section class="ggwp-quote-card__summary" aria-labelledby="{{ $totalPriceId }}SummaryHeading">
      <div class="ggwp-quote-card__summary-top">
        <h4 id="{{ $totalPriceId }}SummaryHeading" class="ggwp-quote-card__section-title">Order summary</h4>
      </div>

      <div class="ggwp-quote-card__route" aria-label="Selected rank route">
        <span class="ggwp-quote-card__route-value" data-quote-summary-current>{{ $summaryCurrentFallback }}</span>
        <span class="ggwp-quote-card__route-arrow" aria-hidden="true">
          <svg class="ggwp-quote-card__route-arrow-icon" viewBox="0 0 20 20" focusable="false">
            <path d="M4.5 10h10.25M10.9 5.9 15 10l-4.1 4.1" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
        </span>
        <span class="ggwp-quote-card__route-value" data-quote-summary-target>{{ $summaryTargetText ?: $summaryTargetFallback }}</span>
      </div>

      <dl class="ggwp-quote-card__meta">
        <div>
          <dt>Region</dt>
          <dd data-quote-summary-region>{{ $summaryRegionFallback }}</dd>
        </div>
        <div>
          <dt>Platform</dt>
          <dd data-quote-summary-platform>{{ $summaryPlatformFallback }}</dd>
        </div>
        <div>
          <dt>Queue</dt>
          <dd data-quote-summary-mode>{{ $summaryModeText ?: $summaryModeFallback }}</dd>
        </div>
      </dl>
    </section>

    <dl class="ggwp-quote-card__lines">
      <div class="ggwp-quote-card__line">
        <dt>
          Estimated delivery
          <span class="ggwp-quote-card__helper" id="{{ $deliveryHelperId }}">Typical completion window. Final timing is confirmed after order review.</span>
        </dt>
        <dd>{{ $duration }}</dd>
      </div>
      <div class="ggwp-quote-card__line">
        <dt>Base rate</dt>
        <dd id="{{ $basePriceId }}">$0.00</dd>
      </div>
      <div class="ggwp-quote-card__line">
        <dt>Add-ons</dt>
        <dd id="{{ $addonPriceId }}">+$0.00</dd>
      </div>
    </dl>

    <div class="ggwp-quote-card__total">
      <div class="ggwp-quote-card__total-label">Estimated total</div>
      <div class="ggwp-quote-card__total-value text-danger" id="{{ $totalPriceId }}" aria-live="polite">$0.00</div>
    </div>

    @isset($priceStateId)
      <div class="small text-secondary mb-2" id="{{ $priceStateId }}" aria-live="polite">Preparing the latest quote...</div>
    @endisset
    @isset($priceErrorId)
      <div class="alert alert-warning small d-none mt-3 mb-0" id="{{ $priceErrorId }}"></div>
    @endisset
    @isset($priceRetryButtonId)
      <div class="d-none mt-2" id="{{ $priceRetryWrapId }}">
        <button class="btn btn-outline-light btn-sm" type="button" id="{{ $priceRetryButtonId }}">Retry price</button>
      </div>
    @endisset

    <div class="ggwp-quote-card__cta">
      @if($checkoutBlockedForBooster)
        <a class="btn btn-outline-light" href="{{ route('booster-dashboard') }}">Open Booster Dashboard</a>
        <div class="small text-warning">Booster accounts cannot place customer orders.</div>
      @else
        <a class="btn btn-danger ggwp-quote-card__button" id="{{ $checkoutButtonId }}" href="{{ route('checkout') }}">Continue to Checkout</a>
        <div class="ggwp-quote-card__checkout-notes" aria-label="Checkout reassurance">
          <span>No payment yet</span>
          <span>Secure checkout</span>
          <span>Verified boosters</span>
        </div>
      @endif
    </div>
  </div>
</aside>
