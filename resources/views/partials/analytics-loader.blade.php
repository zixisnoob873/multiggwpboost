@php
    $analyticsConsentAllowed = (bool) ($analyticsConsentAllowed ?? false);
    $googleMeasurementId = trim((string) config('analytics.google.measurement_id', ''));
    $postHogKey = trim((string) config('analytics.posthog.key', ''));
    $postHogHost = rtrim(trim((string) config('analytics.posthog.host', 'https://us.i.posthog.com')), '/');
    $postHogDefaults = trim((string) config('analytics.posthog.defaults', '2026-01-30'));
    $analyticsHasProviders = $googleMeasurementId !== '' || $postHogKey !== '';
    $queuedAnalyticsEvents = session('analyticsEvents', []);
    $allowedAnalyticsPayloadKeys = [
        'addon_count',
        'addon_label',
        'addon_slug',
        'checkout_kind',
        'component',
        'context',
        'cta',
        'cta_id',
        'field',
        'field_type',
        'game_name',
        'game_slug',
        'has_addons',
        'has_order_reference',
        'has_promo',
        'href_path',
        'is_logged_in',
        'label',
        'location',
        'payment_method',
        'provider',
        'selected',
        'service_name',
        'service_slug',
        'service_type',
        'source',
    ];

    if (! is_array($queuedAnalyticsEvents)) {
        $queuedAnalyticsEvents = [];
    }

    $queuedAnalyticsEvents = collect($queuedAnalyticsEvents)
        ->map(static function (mixed $event) use ($allowedAnalyticsPayloadKeys): ?array {
            if (! is_array($event)) {
                return null;
            }

            $name = strtolower(trim((string) ($event['name'] ?? $event['event'] ?? '')));
            $name = trim((string) preg_replace('/[^a-z0-9_]+/', '_', $name), '_');

            if ($name === '') {
                return null;
            }

            $payload = [];
            $properties = is_array($event['payload'] ?? null)
                ? $event['payload']
                : (is_array($event['properties'] ?? null) ? $event['properties'] : []);

            foreach ($properties as $key => $value) {
                if (! in_array($key, $allowedAnalyticsPayloadKeys, true)) {
                    continue;
                }

                if (is_bool($value)) {
                    $payload[$key] = $value;
                    continue;
                }

                if (is_int($value) || is_float($value)) {
                    $payload[$key] = is_finite((float) $value) ? $value : null;
                    continue;
                }

                if (is_string($value)) {
                    $value = trim((string) preg_replace('/\s+/', ' ', $value));

                    if ($value !== '') {
                        $payload[$key] = substr($value, 0, 120);
                    }
                }
            }

            return [
                'name' => substr($name, 0, 80),
                'payload' => array_filter($payload, static fn (mixed $value): bool => $value !== null),
            ];
        })
        ->filter()
        ->values()
        ->all();

    $analyticsClientConfig = [
        'enabled' => $analyticsConsentAllowed && $analyticsHasProviders,
        'hasGoogle' => $googleMeasurementId !== '',
        'hasPostHog' => $postHogKey !== '',
        'googleMeasurementId' => $googleMeasurementId,
        'posthogKey' => $postHogKey,
        'posthogHost' => $postHogHost,
        'posthogDefaults' => $postHogDefaults,
    ];
@endphp

<script nonce="{{ $cspNonce ?? '' }}">
    window.ggwpAnalyticsConfig = @json($analyticsClientConfig);
    window.ggwpQueuedAnalyticsEvents = @json(array_values($queuedAnalyticsEvents));
</script>

@if($analyticsConsentAllowed && $googleMeasurementId !== '')
    <script nonce="{{ $cspNonce ?? '' }}" data-ggwp-consent-script="analytics-google" async src="https://www.googletagmanager.com/gtag/js?id={{ rawurlencode($googleMeasurementId) }}"></script>
    <script nonce="{{ $cspNonce ?? '' }}">
        window.dataLayer = window.dataLayer || [];
        window.gtag = window.gtag || function gtag() {
            window.dataLayer.push(arguments);
        };
        window.gtag('js', new Date());
        window.gtag('config', @json($googleMeasurementId));
        window.ggwpAnalyticsLoaded = true;
    </script>
@endif

@if($analyticsConsentAllowed && $postHogKey !== '')
    <script nonce="{{ $cspNonce ?? '' }}" data-ggwp-consent-script="analytics-posthog-loader">
        (() => {
            const key = @json($postHogKey);
            const host = @json($postHogHost);
            const defaults = @json($postHogDefaults);

            if (!key || !host || window.ggwpPostHogLoaded) {
                return;
            }

            window.posthog = window.posthog || [];
            window.posthog._i = window.posthog._i || [];
            window.posthog.people = window.posthog.people || [];

            [
                'capture',
                'identify',
                'alias',
                'reset',
                'register',
                'register_once',
                'opt_out_capturing',
                'opt_in_capturing',
            ].forEach((method) => {
                if (typeof window.posthog[method] !== 'function') {
                    window.posthog[method] = function posthogQueueMethod() {
                        window.posthog.push([method].concat(Array.prototype.slice.call(arguments)));
                    };
                }
            });

            if (!document.querySelector('[data-ggwp-consent-script="analytics-posthog"]')) {
                const script = document.createElement('script');
                script.async = true;
                script.src = `${host.replace(/\/$/, '')}/static/array.js`;
                script.dataset.ggwpConsentScript = 'analytics-posthog';
                document.head.appendChild(script);
            }

            window.posthog._i.push([key, {
                api_host: host,
                defaults,
                person_profiles: 'never',
            }, 'posthog']);
            window.ggwpPostHogLoaded = true;
        })();
    </script>
@endif
