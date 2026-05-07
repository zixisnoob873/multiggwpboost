@php
    $items = array_values($items ?? []);
    $firstItem = $items[0] ?? null;
    $target = $firstItem['to'] ?? null;
@endphp

@if($firstItem)
    <aside class="ggwp-social-proof-demo" data-public-social-proof aria-live="polite" aria-atomic="true">
        <script nonce="{{ $cspNonce ?? '' }}" type="application/json" data-public-social-proof-items>@json($items)</script>

        <section class="ggwp-social-proof-demo__card" role="status">
            <div class="ggwp-social-proof-demo__head">
                <span class="ggwp-social-proof-demo__badge">
                    <span class="ggwp-social-proof-demo__dot" aria-hidden="true"></span>
                    Recent Orders
                </span>
                <div class="ggwp-social-proof-demo__head-actions">
                    <span class="ggwp-social-proof-demo__time" data-public-social-proof-time>{{ $firstItem['timeLabel'] ?? '' }}</span>
                    <button
                        class="ggwp-social-proof-demo__toggle"
                        type="button"
                        data-public-social-proof-toggle
                        aria-controls="publicSocialProofBody"
                        aria-expanded="true"
                        aria-label="Collapse recent orders widget"
                    >
                        <span class="ggwp-social-proof-demo__toggle-icon" data-public-social-proof-toggle-icon aria-hidden="true">&minus;</span>
                    </button>
                </div>
            </div>

            <div class="ggwp-social-proof-demo__body" id="publicSocialProofBody">
                <span class="ggwp-social-proof-demo__avatar" data-public-social-proof-avatar>{{ $firstItem['initials'] ?? 'C' }}</span>

                <div class="ggwp-social-proof-demo__content">
                    <p class="ggwp-social-proof-demo__message">
                        <span class="ggwp-social-proof-demo__name" data-public-social-proof-name>{{ $firstItem['customer'] ?? 'Customer' }}</span>
                        just bought
                        <span class="ggwp-social-proof-demo__service" data-public-social-proof-service>{{ $firstItem['service'] ?? 'Rank Boosting' }}</span>
                    </p>

                    <div class="ggwp-social-proof-demo__progression">
                        <div class="ggwp-social-proof-demo__rank">
                            <img
                                src="{{ $firstItem['from']['icon'] ?? '' }}"
                                alt="{{ ($firstItem['from']['label'] ?? 'Current rank') . ' icon' }}"
                                class="ggwp-social-proof-demo__rank-icon"
                                data-public-social-proof-from-icon
                                width="32"
                                height="32"
                                loading="lazy"
                                decoding="async"
                            >
                            <span class="ggwp-social-proof-demo__rank-copy">
                                <span class="ggwp-social-proof-demo__rank-label">Current</span>
                                <span class="ggwp-social-proof-demo__rank-name" data-public-social-proof-from>{{ $firstItem['from']['label'] ?? 'Unranked' }}</span>
                            </span>
                        </div>

                        <span class="ggwp-social-proof-demo__arrow" aria-hidden="true">&rarr;</span>

                        <div class="ggwp-social-proof-demo__rank ggwp-social-proof-demo__rank--target {{ $target ? '' : 'ggwp-social-proof-demo__rank--goal' }}">
                            <img
                                src="{{ $target['icon'] ?? '' }}"
                                alt="{{ (($target['label'] ?? ($firstItem['goal'] ?? 'Goal')) . ' icon') }}"
                                class="ggwp-social-proof-demo__rank-icon"
                                data-public-social-proof-to-icon
                                width="32"
                                height="32"
                                loading="lazy"
                                decoding="async"
                                @if(empty($target['icon'])) hidden @endif
                            >
                            <span class="ggwp-social-proof-demo__rank-copy">
                                <span class="ggwp-social-proof-demo__rank-label" data-public-social-proof-target-label>{{ $target ? 'Desired' : 'Goal' }}</span>
                                <span class="ggwp-social-proof-demo__rank-name" data-public-social-proof-to>{{ $target['label'] ?? ($firstItem['goal'] ?? 'Progress target') }}</span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </aside>
@endif
