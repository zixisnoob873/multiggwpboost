@props([
    'config' => [],
    'estimatedDelivery' => [],
])

@php
    $calculatorId = 'service-calculator-'.\Illuminate\Support\Str::slug(($config['gameSlug'] ?? 'game').'-'.($config['serviceSlug'] ?? 'service'));
    $currentRankId = $calculatorId.'-current-rank';
    $desiredRankId = $calculatorId.'-desired-rank';
    $queueTypeId = $calculatorId.'-queue-type';
    $configId = $calculatorId.'-config';
    $rankOptions = collect($config['rankOptions'] ?? [])->filter()->values();
    $targetRankOptions = collect($config['targetRankOptions'] ?? $rankOptions)->filter()->values();
    $queueOptions = collect($config['queueOptions'] ?? [])->values();
    $addons = collect($config['addons'] ?? [])->values();
    $serviceName = $config['serviceName'] ?? 'Service';
    $gameShortName = $config['gameShortName'] ?? $config['gameName'] ?? 'Game';
@endphp

<section
    id="serviceCalculator"
    class="section-block ggwp-service-calculator"
    aria-labelledby="{{ $calculatorId }}-heading"
    data-service-calculator
    data-service-calculator-config="{{ $configId }}"
    data-analytics-context="service_calculator"
    data-analytics-game-slug="{{ $config['gameSlug'] ?? '' }}"
    data-analytics-game-name="{{ $config['gameName'] ?? '' }}"
    data-analytics-service-slug="{{ $config['serviceSlug'] ?? '' }}"
    data-analytics-service-name="{{ $serviceName }}"
>
    <script id="{{ $configId }}" type="application/json">@json($config)</script>

    <div class="ggwp-service-pricing-grid">
        <div class="ggwp-service-pricing-grid__config">
            <div class="card app-card ggwp-service-config-card">
                <div class="card-body p-4 p-xl-5">
                    <span class="ggwp-home-section-kicker">Pricing Calculator</span>
                    <h2 id="{{ $calculatorId }}-heading" class="h3 mt-2 mb-2">{{ $serviceName }} pricing</h2>
                    <p class="text-secondary mb-4">Configure the core order details and add-ons. The final quote is recalculated by the server before checkout.</p>

                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="form-label" for="{{ $currentRankId }}">Current Rank</label>
                            <select id="{{ $currentRankId }}" class="form-select" data-service-field="currentRank" required>
                                @foreach($rankOptions as $rankOption)
                                    <option value="{{ $rankOption }}" @selected($rankOption === data_get($config, 'defaults.currentRank'))>{{ $rankOption }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="{{ $desiredRankId }}">Desired Rank</label>
                            <select id="{{ $desiredRankId }}" class="form-select" data-service-field="desiredRank" required>
                                @foreach($targetRankOptions as $rankOption)
                                    <option value="{{ $rankOption }}" @selected($rankOption === data_get($config, 'defaults.desiredRank'))>{{ $rankOption }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="{{ $queueTypeId }}">Queue Type</label>
                            <select id="{{ $queueTypeId }}" class="form-select" data-service-field="queueType">
                                @foreach($queueOptions as $queueOption)
                                    <option value="{{ $queueOption['value'] }}" @selected($queueOption['value'] === data_get($config, 'defaults.queueType', 'normal'))>{{ $queueOption['label'] }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6">
                            <div class="ggwp-service-calculator__mini-panel" aria-live="polite">
                                <span>Starting price</span>
                                <strong>{{ $config['startingPriceLabel'] ?? 'Custom quote' }}</strong>
                            </div>
                        </div>
                    </div>

                    <section class="mt-5" aria-labelledby="{{ $calculatorId }}-addons-heading">
                        <div class="d-flex flex-wrap align-items-end justify-content-between gap-2 mb-3">
                            <div>
                                <span class="ggwp-home-section-kicker">Addons Section</span>
                                <h3 id="{{ $calculatorId }}-addons-heading" class="h5 mb-0">Optional {{ $gameShortName }} add-ons</h3>
                            </div>
                        </div>

                        <div class="row g-3 ggwp-addon-grid" data-service-addon-grid>
                            @foreach($addons as $addon)
                                @php($addonId = $calculatorId.'-addon-'.$addon['slug'])
                                <div class="col-md-6">
                                    <div class="ggwp-addon-option">
                                        <label class="form-check ggwp-addon-check" for="{{ $addonId }}">
                                            <input
                                                class="form-check-input ggwp-addon-check__input"
                                                type="checkbox"
                                                id="{{ $addonId }}"
                                                value="{{ $addon['payloadLabel'] ?? $addon['label'] }}"
                                                data-service-addon
                                                data-addon-slug="{{ $addon['slug'] }}"
                                                data-addon-label="{{ $addon['label'] }}"
                                                data-addon-value="{{ $addon['payloadLabel'] ?? $addon['label'] }}"
                                                @if(! empty($addon['controlsQueue'])) data-addon-controls-queue="self_play" @endif
                                                @if(! empty($addon['flag'])) data-addon-flag="{{ $addon['flag'] }}" @endif
                                            >
                                            <span class="ggwp-addon-check__content">
                                                <span class="ggwp-addon-check__title">{{ $addon['label'] }}</span>
                                                @if(empty($addon['available']))
                                                    <span class="ggwp-service-calculator__addon-note">configured at checkout</span>
                                                @endif
                                            </span>
                                        </label>
                                        <button
                                            class="ggwp-addon-info"
                                            type="button"
                                            data-bs-toggle="tooltip"
                                            data-bs-placement="top"
                                            data-bs-title="{{ $addon['description'] }}"
                                            aria-label="More information about {{ $addon['label'] }}"
                                        >
                                            <img
                                                src="{{ asset('assets/info_button.png') }}"
                                                alt=""
                                                class="ggwp-addon-info__icon"
                                                aria-hidden="true"
                                                loading="lazy"
                                                decoding="async"
                                            >
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </section>
                </div>
            </div>
        </div>

        <aside class="ggwp-service-pricing-grid__quote">
            <div class="card app-card ggwp-quote-card" aria-labelledby="{{ $calculatorId }}-quote-heading">
                <div class="card-body p-4 p-xl-5">
                    <div class="ggwp-quote-card__header">
                        <span class="ggwp-home-section-kicker">Instant Quote</span>
                        <h3 id="{{ $calculatorId }}-quote-heading" class="h4 mb-0">{{ $serviceName }} order</h3>
                        <p class="text-secondary mb-0">Server-confirmed pricing updates as you change selections.</p>
                    </div>

                    <ul class="ggwp-quote-card__trust" aria-label="Service trust signals">
                        <li>Secure checkout</li>
                        <li>No payment yet</li>
                        <li>Verified boosters</li>
                    </ul>

                    <dl class="ggwp-quote-card__lines">
                        <div class="ggwp-quote-card__line">
                            <dt>Estimated delivery</dt>
                            <dd data-service-delivery>{{ $estimatedDelivery['label'] ?? 'Confirmed after review' }}</dd>
                        </div>
                        <div class="ggwp-quote-card__line">
                            <dt>Base rate</dt>
                            <dd data-service-price-base>$0.00</dd>
                        </div>
                        <div class="ggwp-quote-card__line">
                            <dt>Add-ons</dt>
                            <dd data-service-price-addons>+$0.00</dd>
                        </div>
                    </dl>

                    <div class="ggwp-quote-card__total">
                        <div class="ggwp-quote-card__total-label">Estimated total</div>
                        <div class="ggwp-quote-card__total-value text-danger" data-service-price-total aria-live="polite">$0.00</div>
                    </div>

                    <div class="small text-secondary mb-2" data-service-price-state aria-live="polite">Preparing the latest quote...</div>
                    <div class="alert alert-warning small d-none mt-3 mb-0" data-service-price-error role="alert"></div>

                    <div class="ggwp-quote-card__cta">
                        <a
                            class="btn btn-danger ggwp-quote-card__button"
                            href="{{ $config['checkoutUrl'] ?? route('checkout') }}"
                            data-service-checkout
                            data-conversion-cta="service-calculator-checkout"
                            data-analytics-context="service_calculator"
                            data-analytics-service-slug="{{ $config['serviceSlug'] ?? '' }}"
                            data-analytics-service-name="{{ $serviceName }}"
                            data-analytics-game-slug="{{ $config['gameSlug'] ?? '' }}"
                            data-analytics-game-name="{{ $config['gameName'] ?? '' }}"
                        >
                            Start Order
                        </a>
                        <div class="ggwp-quote-card__checkout-notes" aria-label="Checkout reassurance">
                            <span>Server recalculated</span>
                            <span>Secure checkout</span>
                            <span>Validated add-ons</span>
                        </div>
                    </div>
                </div>
            </div>
        </aside>
    </div>
</section>
