<!doctype html>
<html lang="en">
<head>
    @php
        $cookieConsent = \App\Support\Privacy\CookieConsent::fromRequest(request());
        $canLoadAnalytics = \App\Support\Privacy\CookieConsent::allows(
            $cookieConsent,
            \App\Support\Privacy\CookieConsent::CATEGORY_ANALYTICS
        );
    @endphp

    @include('partials.analytics-loader', ['analyticsConsentAllowed' => $canLoadAnalytics])
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Site Maintenance | GGWP-Boost</title>
    @include('partials.favicons')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@299&display=swap" rel="stylesheet">
    <style>
        :root {
            --ggwp-bg: #0f1923;
            --ggwp-panel: #121f2b;
            --ggwp-line: rgba(236, 232, 225, 0.14);
            --ggwp-copy: #ece8e1;
            --ggwp-muted: rgba(236, 232, 225, 0.72);
            --ggwp-primary: #ff4655;
            --ggwp-gold: #d4b06a;
        }

        body {
            min-height: 100vh;
            margin: 0;
            background:
                linear-gradient(rgba(236, 232, 225, 0.035) 1px, transparent 1px),
                linear-gradient(90deg, rgba(236, 232, 225, 0.03) 1px, transparent 1px),
                radial-gradient(circle at 14% 10%, rgba(255, 70, 85, 0.22), transparent 26%),
                radial-gradient(circle at 86% 82%, rgba(212, 176, 106, 0.12), transparent 28%),
                linear-gradient(180deg, var(--ggwp-bg) 0%, #08111a 100%);
            background-size: 34px 34px, 34px 34px, auto, auto, auto;
            color: var(--ggwp-copy);
            font-family: "Outfit", sans-serif;
            font-optical-sizing: auto;
            font-weight: 299;
            font-style: normal;
        }

        body * {
            font-family: "Outfit", sans-serif;
            font-optical-sizing: auto;
            font-weight: 299 !important;
            font-style: normal;
        }

        .maintenance-shell {
            min-height: 100vh;
            display: grid;
            align-items: center;
            padding: clamp(1rem, 4vw, 3rem);
        }

        .maintenance-card {
            width: min(980px, 100%);
            margin-inline: auto;
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(220px, 0.45fr);
            gap: clamp(1rem, 3vw, 2rem);
            padding: clamp(1.2rem, 4vw, 2.5rem);
            border-radius: 1.35rem;
            border: 1px solid var(--ggwp-line);
            background:
                linear-gradient(135deg, rgba(255, 70, 85, 0.1), transparent 34%),
                linear-gradient(180deg, rgba(255, 255, 255, 0.065), rgba(255, 255, 255, 0.025)),
                rgba(18, 31, 43, 0.94);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.05), 0 28px 70px rgba(0, 0, 0, 0.38);
        }

        .maintenance-kicker {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            color: var(--ggwp-gold);
            font-size: 0.76rem;
            font-weight: 800;
            letter-spacing: 0.14em;
            text-transform: uppercase;
        }

        .maintenance-kicker::before {
            content: "";
            width: 0.55rem;
            height: 0.55rem;
            border-radius: 2px;
            background: var(--ggwp-primary);
            box-shadow: 0 0 0 0.22rem rgba(255, 70, 85, 0.14);
        }

        .maintenance-title {
            max-width: 12ch;
            margin: 0.55rem 0 0.8rem;
            font-size: clamp(2rem, 6vw, 4.75rem);
            font-weight: 900;
            letter-spacing: 0;
            line-height: 0.95;
        }

        .maintenance-copy {
            margin: 0;
            font-size: clamp(1.05rem, 2vw, 1.25rem);
            line-height: 1.7;
            color: var(--ggwp-muted);
        }

        .maintenance-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            margin-top: 1.35rem;
        }

        .maintenance-button {
            min-height: 44px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.78rem 1rem;
            border-radius: 0.85rem;
            border: 1px solid rgba(255, 70, 85, 0.5);
            background: linear-gradient(180deg, var(--ggwp-primary), #d92d3b);
            color: #ffffff;
            font-size: 0.82rem;
            font-weight: 800;
            letter-spacing: 0.04em;
            text-decoration: none;
            text-transform: uppercase;
            box-shadow: 0 16px 32px rgba(255, 70, 85, 0.24);
            transition: transform 0.2s ease, filter 0.2s ease, box-shadow 0.2s ease;
        }

        .maintenance-button:hover,
        .maintenance-button:focus-visible {
            color: #ffffff;
            filter: brightness(1.04);
            transform: translateY(-1px);
            box-shadow: 0 20px 42px rgba(255, 70, 85, 0.3);
            outline: none;
        }

        .maintenance-status {
            display: grid;
            align-content: end;
            gap: 0.75rem;
        }

        .maintenance-status__item {
            padding: 1rem;
            border-radius: 1rem;
            border: 1px solid rgba(236, 232, 225, 0.1);
            background: rgba(8, 17, 26, 0.42);
        }

        .maintenance-status__label {
            display: block;
            color: var(--ggwp-gold);
            font-size: 0.7rem;
            font-weight: 800;
            letter-spacing: 0.11em;
            text-transform: uppercase;
        }

        .maintenance-status__value {
            display: block;
            margin-top: 0.28rem;
            color: #ffffff;
            font-weight: 800;
            line-height: 1.35;
        }

        .ggwp-discord-link {
            color: var(--ggwp-primary);
            font-weight: 700;
            text-decoration: none;
            transition: color 0.2s ease, text-shadow 0.2s ease, filter 0.2s ease;
        }

        .ggwp-discord-link:hover,
        .ggwp-discord-link:focus-visible {
            color: #ff6b76;
            text-shadow: 0 0 0.65rem rgba(255, 70, 85, 0.6), 0 0 1.25rem rgba(255, 70, 85, 0.34);
            filter: brightness(1.04);
            outline: none;
        }

        @media (max-width: 767.98px) {
            .maintenance-card {
                grid-template-columns: 1fr;
            }

            .maintenance-title {
                max-width: 10ch;
            }
        }
    </style>
</head>
<body>
    <main class="maintenance-shell">
        <section class="maintenance-card" aria-labelledby="maintenance-message">
            <div>
                <span class="maintenance-kicker">GGWP-Boost status</span>
                <h1 id="maintenance-message" class="maintenance-title">Back in the queue shortly</h1>
                <p class="maintenance-copy">
                    Website is under maintenance right now, please visit back in 5-10 minutes. If you want to place your order urgent, join our
                    <a href="{{ $discordUrl }}" class="ggwp-discord-link" target="_blank" rel="noopener noreferrer">Discord</a>
                    and open a ticket, our support will be in touch with you within 5 minutes.
                </p>
                <div class="maintenance-actions">
                    <a href="{{ $discordUrl }}" class="maintenance-button" target="_blank" rel="noopener noreferrer">Open Discord support</a>
                </div>
            </div>

            <aside class="maintenance-status" aria-label="Maintenance status">
                <div class="maintenance-status__item">
                    <span class="maintenance-status__label">Current state</span>
                    <span class="maintenance-status__value">Scheduled platform work</span>
                </div>
                <div class="maintenance-status__item">
                    <span class="maintenance-status__label">Order support</span>
                    <span class="maintenance-status__value">Available through Discord tickets</span>
                </div>
                <div class="maintenance-status__item">
                    <span class="maintenance-status__label">ETA</span>
                    <span class="maintenance-status__value">Usually 5-10 minutes</span>
                </div>
            </aside>
        </section>
    </main>
    @include('partials.tawk-widget')
</body>
</html>
