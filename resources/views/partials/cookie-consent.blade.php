@php
    use App\Support\Privacy\CookieConsent;

    $cookieConsent = is_array($cookieConsent ?? null)
        ? $cookieConsent
        : CookieConsent::fromRequest(request());

    $shouldShowCookieBanner = (bool) ($shouldShowCookieBanner ?? CookieConsent::shouldShowBanner($cookieConsent));
    $cookieConsentCategories = is_array($cookieConsent['categories'] ?? null)
        ? $cookieConsent['categories']
        : [];

    $cookieConsentConfig = [
        'cookieName' => CookieConsent::COOKIE_NAME,
        'version' => CookieConsent::VERSION,
        'expiresDays' => 180,
        'secure' => request()->isSecure(),
        'showBanner' => $shouldShowCookieBanner,
        'currentConsent' => CookieConsent::isCurrent($cookieConsent) ? $cookieConsent : null,
        'analyticsMeasurementId' => 'G-9J3GNV5WSX',
        'supportAllowedOnRoute' => (bool) ($shouldLoadTawkWidget ?? false),
        'supportPropertyId' => '69f292b86de35f1c378f957f',
        'supportWidgetId' => '1jndoq8fv',
    ];
@endphp

<section
    class="ggwp-cookie-consent"
    data-cookie-consent
    role="region"
    aria-labelledby="ggwpCookieConsentTitle"
    @if(! $shouldShowCookieBanner) hidden @endif
>
    <div class="ggwp-cookie-consent__inner">
        <div class="ggwp-cookie-consent__copy">
            <p class="ggwp-cookie-consent__eyebrow">Privacy choices</p>
            <h2 id="ggwpCookieConsentTitle" class="ggwp-cookie-consent__title">Cookie preferences</h2>
            <p class="ggwp-cookie-consent__text">
                Necessary cookies stay on for login, forms, CSRF protection, checkout, and security. You can also allow analytics and live support chat.
            </p>
        </div>

        <div class="ggwp-cookie-consent__actions" aria-label="Cookie consent actions">
            <button type="button" class="btn btn-danger ggwp-cookie-consent__button" data-cookie-accept-all>
                Accept all
            </button>
            <button type="button" class="btn btn-outline-light ggwp-cookie-consent__button" data-cookie-decline>
                Decline non-essential
            </button>
            <button type="button" class="btn btn-link ggwp-cookie-consent__link" data-cookie-customize>
                Customize
            </button>
        </div>
    </div>
</section>

<div
    class="ggwp-cookie-modal"
    data-cookie-modal
    role="dialog"
    aria-modal="true"
    aria-labelledby="ggwpCookieModalTitle"
    aria-describedby="ggwpCookieModalDescription"
    hidden
>
    <div class="ggwp-cookie-modal__backdrop" data-cookie-modal-close></div>
    <div class="ggwp-cookie-modal__panel" role="document">
        <header class="ggwp-cookie-modal__header">
            <div>
                <p class="ggwp-cookie-consent__eyebrow">Manage cookies</p>
                <h2 id="ggwpCookieModalTitle" class="ggwp-cookie-modal__title">Choose what GGWP Boost can load</h2>
            </div>
            <button type="button" class="ggwp-cookie-modal__close" data-cookie-modal-close aria-label="Close cookie preferences">
                &times;
            </button>
        </header>

        <div class="ggwp-cookie-modal__body">
            <p id="ggwpCookieModalDescription" class="ggwp-cookie-modal__description">
                Necessary cookies are always enabled. Optional categories only load after you allow them.
            </p>

            <fieldset class="ggwp-cookie-modal__fieldset">
                <legend class="ggwp-cookie-modal__legend">Cookie categories</legend>

                <div class="ggwp-cookie-category">
                    <div class="ggwp-cookie-category__copy">
                        <label class="ggwp-cookie-category__label" for="ggwpCookieNecessary">
                            Necessary
                        </label>
                        <p id="ggwpCookieNecessaryHelp" class="ggwp-cookie-category__description">
                            Required for the Laravel session, XSRF token, remember-me login when selected, forms, checkout, and security.
                        </p>
                    </div>
                    <div class="form-check form-switch ggwp-cookie-category__switch">
                        <input
                            class="form-check-input"
                            type="checkbox"
                            role="switch"
                            id="ggwpCookieNecessary"
                            checked
                            disabled
                            aria-describedby="ggwpCookieNecessaryHelp"
                            data-cookie-category="necessary"
                        >
                    </div>
                </div>

                <div class="ggwp-cookie-category">
                    <div class="ggwp-cookie-category__copy">
                        <label class="ggwp-cookie-category__label" for="ggwpCookieAnalytics">
                            Analytics
                        </label>
                        <p id="ggwpCookieAnalyticsHelp" class="ggwp-cookie-category__description">
                            Allows Google Analytics to help us understand page performance and improve the site.
                        </p>
                    </div>
                    <div class="form-check form-switch ggwp-cookie-category__switch">
                        <input
                            class="form-check-input"
                            type="checkbox"
                            role="switch"
                            id="ggwpCookieAnalytics"
                            aria-describedby="ggwpCookieAnalyticsHelp"
                            data-cookie-category="analytics"
                            @if((bool) ($cookieConsentCategories[CookieConsent::CATEGORY_ANALYTICS] ?? false)) checked @endif
                        >
                    </div>
                </div>

                <div class="ggwp-cookie-category">
                    <div class="ggwp-cookie-category__copy">
                        <label class="ggwp-cookie-category__label" for="ggwpCookieSupport">
                            Support chat
                        </label>
                        <p id="ggwpCookieSupportHelp" class="ggwp-cookie-category__description">
                            Allows Tawk.to live chat on pages where live support is available.
                        </p>
                    </div>
                    <div class="form-check form-switch ggwp-cookie-category__switch">
                        <input
                            class="form-check-input"
                            type="checkbox"
                            role="switch"
                            id="ggwpCookieSupport"
                            aria-describedby="ggwpCookieSupportHelp"
                            data-cookie-category="support"
                            @if((bool) ($cookieConsentCategories[CookieConsent::CATEGORY_SUPPORT] ?? false)) checked @endif
                        >
                    </div>
                </div>
            </fieldset>

            <p class="ggwp-cookie-modal__note">
                Checkout state may also be saved in this browser so an in-progress order or promo code can continue. That storage is not a cookie.
            </p>
        </div>

        <footer class="ggwp-cookie-modal__footer">
            <button type="button" class="btn btn-danger ggwp-cookie-consent__button" data-cookie-save-preferences>
                Save choices
            </button>
            <button type="button" class="btn btn-outline-light ggwp-cookie-consent__button" data-cookie-modal-close>
                Cancel
            </button>
        </footer>
    </div>
</div>

<div class="visually-hidden" aria-live="polite" data-cookie-consent-status></div>

<script nonce="{{ $cspNonce ?? '' }}">
(() => {
    const config = @json($cookieConsentConfig);
    const banner = document.querySelector('[data-cookie-consent]');
    const modal = document.querySelector('[data-cookie-modal]');
    const status = document.querySelector('[data-cookie-consent-status]');
    const focusableSelector = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';
    let previousFocus = null;

    const getCookieValue = (name) => {
        const cookie = document.cookie
            .split('; ')
            .find((entry) => entry.startsWith(`${name}=`));

        return cookie ? cookie.slice(name.length + 1) : '';
    };

    const normalizeConsent = (payload) => {
        if (!payload || Number(payload.version) !== Number(config.version) || typeof payload.categories !== 'object') {
            return null;
        }

        return {
            version: Number(config.version),
            timestamp: typeof payload.timestamp === 'string' ? payload.timestamp : '',
            categories: {
                necessary: true,
                analytics: Boolean(payload.categories.analytics),
                support: Boolean(payload.categories.support),
            },
        };
    };

    const readConsent = () => {
        const value = getCookieValue(config.cookieName);

        if (!value) {
            return normalizeConsent(config.currentConsent);
        }

        try {
            return normalizeConsent(JSON.parse(decodeURIComponent(value)));
        } catch (error) {
            return null;
        }
    };

    const writeConsent = (categories) => {
        const consent = {
            version: Number(config.version),
            timestamp: new Date().toISOString(),
            categories: {
                necessary: true,
                analytics: Boolean(categories.analytics),
                support: Boolean(categories.support),
            },
        };

        const maxAge = Number(config.expiresDays || 180) * 24 * 60 * 60;
        const secure = config.secure || window.location.protocol === 'https:';
        const cookieParts = [
            `${config.cookieName}=${encodeURIComponent(JSON.stringify(consent))}`,
            `Max-Age=${maxAge}`,
            'Path=/',
            'SameSite=Lax',
        ];

        if (secure) {
            cookieParts.push('Secure');
        }

        document.cookie = cookieParts.join('; ');

        return consent;
    };

    const announce = (message) => {
        if (status) {
            status.textContent = message;
        }
    };

    const syncInputs = (consent) => {
        const categories = consent?.categories || {};

        modal?.querySelectorAll('[data-cookie-category]').forEach((input) => {
            if (!(input instanceof HTMLInputElement)) {
                return;
            }

            if (input.dataset.cookieCategory === 'necessary') {
                input.checked = true;
                return;
            }

            input.checked = Boolean(categories[input.dataset.cookieCategory]);
        });
    };

    const analyticsScriptUrl = () => {
        const origin = `https://www.${'googletagmanager'}.com`;

        return `${origin}/gtag/js?id=${encodeURIComponent(config.analyticsMeasurementId)}`;
    };

    const loadAnalytics = () => {
        if (!config.analyticsMeasurementId) {
            return;
        }

        window[`ga-disable-${config.analyticsMeasurementId}`] = false;
        window.dataLayer = window.dataLayer || [];
        window.gtag = window.gtag || function gtag() {
            window.dataLayer.push(arguments);
        };

        if (!document.querySelector('[data-ggwp-consent-script="analytics"]')) {
            const script = document.createElement('script');
            script.async = true;
            script.src = analyticsScriptUrl();
            script.dataset.ggwpConsentScript = 'analytics';
            document.head.appendChild(script);
        }

        if (!window.ggwpAnalyticsLoaded) {
            window.gtag('js', new Date());
            window.gtag('config', config.analyticsMeasurementId);
            window.ggwpAnalyticsLoaded = true;
        }
    };

    const disableAnalytics = () => {
        if (!config.analyticsMeasurementId) {
            return;
        }

        window[`ga-disable-${config.analyticsMeasurementId}`] = true;
        document.querySelectorAll('[data-ggwp-consent-script="analytics"]').forEach((script) => script.remove());
        window.dataLayer = [];
        window.ggwpAnalyticsLoaded = false;

        try {
            delete window.gtag;
        } catch (error) {
            window.gtag = undefined;
        }
    };

    const supportScriptUrl = () => {
        const host = ['embed', 'tawk', 'to'].join('.');

        return `https://${host}/${config.supportPropertyId}/${config.supportWidgetId}`;
    };

    const loadSupport = () => {
        if (!config.supportAllowedOnRoute || !config.supportPropertyId || !config.supportWidgetId) {
            return;
        }

        if (document.querySelector('[data-ggwp-consent-script="support"]')) {
            window.ggwpSupportLoaded = true;
            return;
        }

        const supportApiName = ['Tawk', 'API'].join('_');
        const supportStartName = ['Tawk', 'LoadStart'].join('_');

        window[supportApiName] = window[supportApiName] || {};
        window[supportStartName] = new Date();

        const script = document.createElement('script');
        script.async = true;
        script.src = supportScriptUrl();
        script.charset = 'UTF-8';
        script.setAttribute('crossorigin', '*');
        script.dataset.ggwpConsentScript = 'support';
        document.head.appendChild(script);
        window.ggwpSupportLoaded = true;
    };

    const disableSupport = () => {
        const supportApiName = ['Tawk', 'API'].join('_');
        const supportStartName = ['Tawk', 'LoadStart'].join('_');

        try {
            window[supportApiName]?.hideWidget?.();
        } catch (error) {
            // Tawk may not be initialized yet; removing our loader is enough for future visits.
        }

        document.querySelectorAll('[data-ggwp-consent-script="support"]').forEach((script) => script.remove());
        window.ggwpSupportLoaded = false;

        try {
            delete window[supportApiName];
            delete window[supportStartName];
        } catch (error) {
            window[supportApiName] = undefined;
            window[supportStartName] = undefined;
        }
    };

    const applyConsent = (consent) => {
        if (consent?.categories?.analytics) {
            loadAnalytics();
        } else {
            disableAnalytics();
        }

        if (consent?.categories?.support) {
            loadSupport();
        } else {
            disableSupport();
        }
    };

    const hideBanner = () => {
        if (banner) {
            banner.hidden = true;
        }
    };

    const showBanner = () => {
        if (banner) {
            banner.hidden = false;
        }
    };

    const closePreferences = () => {
        if (!modal) {
            return;
        }

        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('ggwp-cookie-modal-open');

        if (previousFocus instanceof HTMLElement && previousFocus.isConnected && previousFocus.offsetParent !== null) {
            previousFocus.focus({ preventScroll: true });
            return;
        }

        const fallbackFocus = document.querySelector('[data-cookie-settings]') || document.getElementById('appMain');
        fallbackFocus?.focus?.({ preventScroll: true });
    };

    const openPreferences = (trigger = null) => {
        if (!modal) {
            return;
        }

        previousFocus = trigger instanceof HTMLElement ? trigger : document.activeElement;
        syncInputs(readConsent());
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('ggwp-cookie-modal-open');

        const firstInput = modal.querySelector('[data-cookie-category="analytics"]');
        const firstFocusable = firstInput instanceof HTMLElement
            ? firstInput
            : modal.querySelector(focusableSelector);

        firstFocusable?.focus({ preventScroll: true });
    };

    const saveChoices = (categories, message) => {
        const consent = writeConsent(categories);

        syncInputs(consent);
        applyConsent(consent);
        hideBanner();
        closePreferences();
        announce(message);
    };

    document.addEventListener('click', (event) => {
        const target = event.target instanceof Element ? event.target : null;

        if (!target) {
            return;
        }

        const settingsTrigger = target.closest('[data-cookie-settings], [data-cookie-customize]');

        if (settingsTrigger instanceof HTMLElement) {
            event.preventDefault();
            openPreferences(settingsTrigger);
            return;
        }

        if (target.closest('[data-cookie-accept-all]')) {
            saveChoices({ analytics: true, support: true }, 'All optional cookies accepted.');
            return;
        }

        if (target.closest('[data-cookie-decline]')) {
            saveChoices({ analytics: false, support: false }, 'Optional cookies declined.');
            return;
        }

        if (target.closest('[data-cookie-save-preferences]')) {
            const analytics = modal?.querySelector('[data-cookie-category="analytics"]');
            const support = modal?.querySelector('[data-cookie-category="support"]');

            saveChoices({
                analytics: analytics instanceof HTMLInputElement && analytics.checked,
                support: support instanceof HTMLInputElement && support.checked,
            }, 'Cookie preferences saved.');
            return;
        }

        if (target.closest('[data-cookie-modal-close]')) {
            closePreferences();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (!modal || modal.hidden) {
            return;
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            closePreferences();
            return;
        }

        if (event.key !== 'Tab') {
            return;
        }

        const focusable = Array.from(modal.querySelectorAll(focusableSelector))
            .filter((element) => element instanceof HTMLElement && element.offsetParent !== null);

        if (!focusable.length) {
            return;
        }

        const first = focusable[0];
        const last = focusable[focusable.length - 1];

        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    });

    const consent = readConsent();

    if (consent) {
        hideBanner();
        syncInputs(consent);
        applyConsent(consent);
    } else if (config.showBanner) {
        showBanner();
        syncInputs(null);
    }
})();
</script>
