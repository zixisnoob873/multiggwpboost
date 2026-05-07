# Security Deployment Checklist

Run these checks in the production-like environment before launch and after any infrastructure change.

## Web Entry Point

- Web document root points to Laravel `public/` only.
- Directory listing is disabled.
- `/.env` returns 403 or 404.
- `/composer.lock` returns 403 or 404.
- `/routes/web.php` returns 403 or 404.
- `/storage/framework/sessions` returns 403 or 404.
- `/vendor/composer/installed.json` returns 403 or 404.
- Vite dev server is not publicly exposed.
- WebSockets dashboard is not public.
- Source maps are not publicly exposed unless intentionally approved.

## Environment

- `APP_ENV=production`.
- `APP_DEBUG=false`.
- `APP_URL=https://production-domain`.
- `APP_KEY` is generated and not a placeholder.
- `SESSION_SECURE_COOKIE=true`.
- `SESSION_ENCRYPT=true`.
- `SESSION_HTTP_ONLY=true`.
- `SESSION_SAME_SITE=lax` or `strict`.
- `TRUSTED_PROXIES` is set to the real reverse proxy or load balancer CIDRs.
- Run `php scripts/validate-production-env.php /path/to/production.env`.
- Run `php artisan config:cache` only after verifying the production env.

## Browser Headers

- HSTS header is present over HTTPS.
- CSP header is present and contains `frame-ancestors`.
- `X-Content-Type-Options: nosniff` is present.
- `Referrer-Policy: strict-origin-when-cross-origin` is present.
- `Permissions-Policy` is present.

## Payments And OAuth

- Stripe webhook uses the real signing secret from the production Stripe dashboard.
- Stripe webhook endpoint rejects requests with missing or invalid signatures.
- Cryptomus webhook uses the real API key and verifies signatures.
- `CRYPTOMUS_BASE_URL=https://api.cryptomus.com`.
- `CRYPTOMUS_ALLOWED_HOSTS=api.cryptomus.com`.
- OAuth callback URLs exactly match production URLs in Google and Discord consoles.
- If OAuth account linking is enabled, provider consoles also include the production `/auth/{provider}/link/callback` URLs.

## Data And Host Permissions

- Database user has least privilege required by the app.
- Web server user cannot write application source files.
- Web server user can write only required runtime paths such as `storage/` and `bootstrap/cache/`.
- Backups are encrypted and access-controlled.

## Logging

- Logs do not contain raw passwords.
- Logs do not contain raw checkout tokens.
- Logs do not contain API keys, OAuth tokens, session IDs, payment secrets, or webhook signing secrets.
- Payment, webhook, security, and activity logs rotate according to retention policy.
