<div align="center">
  <img src="public/assets/logo.png" alt="GGWP Boost logo" width="128" />

  <h1>GGWP Boost</h1>

  <p>
    <strong>A production-focused Laravel platform for running a VALORANT boosting business end to end.</strong>
  </p>

  <p>
    <img alt="Laravel 12" src="https://img.shields.io/badge/Laravel-12-FF2D20?style=for-the-badge&logo=laravel&logoColor=white">
    <img alt="PHP 8.2+" src="https://img.shields.io/badge/PHP-8.2+-777BB4?style=for-the-badge&logo=php&logoColor=white">
    <img alt="Vite 7" src="https://img.shields.io/badge/Vite-7-646CFF?style=for-the-badge&logo=vite&logoColor=white">
    <img alt="Tailwind CSS 4" src="https://img.shields.io/badge/Tailwind-4-38BDF8?style=for-the-badge&logo=tailwindcss&logoColor=white">
    <img alt="Stripe" src="https://img.shields.io/badge/Stripe-Checkout-635BFF?style=for-the-badge&logo=stripe&logoColor=white">
    <img alt="WebSockets" src="https://img.shields.io/badge/Realtime-WebSockets-22C55E?style=for-the-badge">
    <img alt="PHPUnit" src="https://img.shields.io/badge/Tests-PHPUnit-8A2BE2?style=for-the-badge">
  </p>
</div>

---

## <a id="jump-to"></a>🌈 Jump To

- [🎯 Overview](#overview)
- [✨ Feature Map](#feature-map)
- [🧱 Tech Stack](#tech-stack)
- [🗺️ Architecture](#architecture)
- [📁 Project Structure](#project-structure)
- [👥 Roles and Access](#roles-and-access)
- [🔄 Core Workflows](#core-workflows)
- [🚀 Local Setup](#local-setup)
- [⚙️ Environment Checklist](#environment-checklist)
- [▶️ Running the App](#running-the-app)
- [🧪 Testing](#testing)
- [🧰 Operations Commands](#operations-commands)
- [💳 Payments](#payments)
- [💬 Realtime Chat](#realtime-chat)
- [📣 Email and Notifications](#email-and-notifications)
- [🛡️ Security Notes](#security-notes)
- [🚢 Deployment](#deployment)
- [🧯 Troubleshooting](#troubleshooting)
- [🧑‍💻 Maintenance Notes](#maintenance-notes)
- [📄 License](#license)

---

## <a id="overview"></a>🎯 Overview

**GGWP Boost** is a Blade-first Laravel 12 application for operating a VALORANT boosting platform. It combines the public storefront, pricing engine, checkout, customer dashboard, booster workspace, admin panel, realtime order chat, CMS content, finance tooling, and operational safety controls in one codebase.

The app is intentionally route-driven and server-rendered. It does **not** expose a maintained standalone REST API. A few JSON endpoints support the Blade frontend for pricing, promo previews, chat, progress updates, readiness checks, and payment webhooks.

### 🕹️ What This Platform Runs

| Area | Purpose |
| --- | --- |
| 🛒 Storefront | Public service pages, pricing calculator, checkout, blog, FAQ, reviews, contact, legal pages, and booster applications |
| 💰 Pricing | Authoritative backend VALORANT pricing for Rank Boosting, Placement Matches, Radiant Boost, and Ranked Wins |
| 💳 Checkout | Durable pending checkout records with Stripe and Cryptomus payment providers |
| 👤 Customer Portal | Order dashboard, chat, progress tracking, pause/resume, extensions, tips, and profile settings |
| 🧑‍🚀 Booster Portal | Claim queue, assigned orders, progress updates, completion proofs, wallet, and withdrawal requests |
| 🛠️ Admin Panel | Orders, chats, customers, boosters, applications, marketing, CMS, pricing editor, finance, system settings, and audit logs |
| 💬 Realtime | Private order chat over Laravel Echo and Pusher-compatible WebSockets |
| 📣 Notifications | Queued customer emails, booster emails, account lifecycle emails, and Discord webhooks |
| 🛡️ Hardening | Rate limits, CSP/security headers, webhook idempotency, startup validation, image scanning, and privacy redaction tools |

---

## <a id="feature-map"></a>✨ Feature Map

### 🎮 Storefront and Pricing

- 🧮 Live quote calculator backed by `App\Support\Pricing\ValorantPricingEngine`
- 🏆 Services: `Rank Boosting`, `Placement Matches`, `Radiant Boost`, `Ranked Wins`
- 🌍 Region modifiers for `NA`, `EU`, `APAC`, and `LATAM`
- 💻 Platform modifiers for `PC` and `Console`
- 🤝 Boost modes for `Account Shared` and `Duo / Self-Play`
- 🎯 RR-aware pricing rules and special high-rank steps
- 🧩 Add-ons: Offline Mode, Specific Agents, One-Trick Agent, Solo-Queue Only, No 5-Stack, Bonus Win, Streaming, Express Order, Normalize Scores, and Record-Clips
- 🧠 Agent-aware validation for Specific Agents and One-Trick Agent selections
- 🏷️ Promo code preview and final consumption during successful checkout finalization
- 🧾 Public CMS pages for home, blog, FAQ, reviews, contact, booster applications, and legal pages

### 🛒 Orders and Checkout

- 💾 `pending_checkouts` are stored before sending a user to a hosted payment provider
- 🔌 Payment provider abstraction for Stripe and Cryptomus
- 🔁 Idempotent checkout finalization from success pages and webhooks
- 🧮 Server-side repricing before order creation
- 🎁 Customer order extensions, booster tips, and admin tips
- 🧑‍💼 Admin manual order creation with optional manual price override
- 🔧 Repair tooling for legacy or duplicate payment identifiers

### 👤 Customer Experience

- 🔐 Email/password signup, login, logout, password reset, and password update
- 🌐 Google and Discord OAuth through Laravel Socialite
- 🧩 OAuth profile completion flow when provider data needs missing local fields
- 🛡️ Login throttling with numeric CAPTCHA after repeated failures
- 📦 Customer dashboard, order list, order detail, upgrade flow, and chat pages
- ⏸️ Pause/resume controls for eligible active paid orders
- ⬆️ Extension checkout flows that recalculate by service type
- 💝 Tip flows for assigned boosters or admin support
- 🖼️ Signed profile photo delivery for private uploads

### 🧑‍🚀 Booster Workspace

- 📥 Pending order claim queue
- 📊 Booster dashboard and assigned order list
- 🧭 Structured progress updates for rank, RR, wins, and placements
- 🖼️ Completion-proof upload before completion
- ✅ Completion workflow for assigned active orders
- 🔄 Drop-to-queue flow for eligible assigned orders
- 👛 Wallet overview and withdrawal requests

### 🛠️ Admin Operations

- 📌 Module-based admin navigation for dashboard, operations, people, marketing, content, finance, and system
- 🧾 Order list, exports, edit screens, status transitions, assignment, chats, and completion-proof download
- 🧪 Manual/custom order creation with authoritative pricing where possible
- 💵 Pricing editor backed by `pricing_settings` and `pricing_setting_revisions`
- 🧑 Customer and booster management with account status controls
- 📝 Booster application review and conversion
- 📣 Reviews, blog articles, promotions, and promo codes
- 🧩 CMS page editing, FAQs, featured boosters, and add-on tooltip overrides
- 💸 Withdrawal approvals/rejections, wallet adjustments, and income statement exports
- 🚧 Hardened maintenance mode with phrase, CAPTCHA, password, and challenge token
- 🧾 Admin audit logs for sensitive operator actions

### 🧰 Operational Tooling

- ✅ Readiness endpoint at `GET /ready`
- 🧹 Scheduled stale pending-checkout pruning
- 🔁 Scheduled Discord notification retry
- 🧼 Privacy redaction command for historical user text
- 🧾 Finance reconciliation for historical withdrawals
- 🔍 Startup configuration validator with stricter staging/production checks

---

## <a id="tech-stack"></a>🧱 Tech Stack

| Layer | Tools |
| --- | --- |
| 🧠 Backend | PHP `^8.2`, Laravel `^12.0`, session-authenticated web app |
| 🎨 Frontend | Blade, Vite `^7`, Tailwind CSS `^4`, Bootstrap `5.3.3` from CDN, custom JS modules |
| ⚡ Realtime | `digiworld/laravel-websockets`, Pusher protocol, Laravel Echo, `pusher-js` |
| 💳 Payments | Stripe Checkout through `stripe/stripe-php`, Cryptomus hosted crypto invoices |
| 🗄️ Data | MySQL by default, SQLite for tests, database-backed sessions/cache/queues |
| 📣 Messaging | Laravel mailables, queued dispatch records, Discord webhooks |
| 🔐 Auth | Laravel auth, password reset tokens, Google OAuth, Discord OAuth |
| 🧪 Testing | PHPUnit `^11.5`, Laravel test helpers, in-memory SQLite |
| 🧹 Quality | Laravel Pint, Laravel Pail, Tinker |

---

## <a id="architecture"></a>🗺️ Architecture

The repository follows a pragmatic Laravel domain layout. Controllers orchestrate HTTP requests, while actions, queries, services, support classes, jobs, observers, and models hold the business behavior.

### 🧭 Request Flow

1. 🌐 Routes are split by audience in `routes/web-routes`.
2. 🧱 Controllers validate and coordinate the request.
3. ⚙️ Actions/services perform write-heavy or cross-cutting business logic.
4. 📚 Queries build dashboard, listing, and public-page read models.
5. 📦 Models persist orders, users, chats, pricing, payments, CMS content, finance records, and logs.
6. 📣 Jobs, mailables, observers, and notifications handle async side effects.

### 🛣️ Route Groups

| File | Audience |
| --- | --- |
| `routes/web-routes/public.php` | Public storefront, pricing, checkout helpers, CMS pages, blog, FAQ, contact, readiness, sitemap, and webhooks |
| `routes/web-routes/auth.php` | Login, signup, logout, password reset, Google OAuth, Discord OAuth, and OAuth profile completion |
| `routes/web-routes/customer.php` | Customer dashboard, orders, chats, checkout, order actions, extensions, and tips |
| `routes/web-routes/booster.php` | Booster dashboard, claim queue, chats, order progress, completion, wallet, and withdrawals |
| `routes/web-routes/admin.php` | Admin dashboard, operations, people, marketing, content, finance, pricing, system settings, maintenance, and audit logs |
| `routes/web-routes/chat.php` | Authenticated chat history/posting and order progress updates |
| `routes/api.php` | Kept only for default Laravel bootstrapping; legacy API endpoints now live in web routes |

### 🧮 Pricing Architecture

- `config/pricing.php` contains the default VALORANT pricing configuration.
- `ValorantPricingConfigRepository` reads active pricing from `pricing_settings` when available.
- Invalid or missing database pricing safely falls back to `config/pricing.php`.
- `ValorantPricingConfigValidator` locks rank/service shape and validates editable pricing sections.
- Admin pricing updates write immutable revision rows to `pricing_setting_revisions`.
- Public pricing config is exposed through `GET /pricing-config` for frontend preview data.

### 💳 Payment Architecture

- `PaymentManager` exposes configured provider descriptors.
- `PendingCheckoutStore` persists pre-payment checkout state.
- `PaymentInitializationPipeline` starts the hosted provider flow.
- `PaymentWebhookEventService` stores webhook processing state and idempotency data.
- `FinalizePendingCheckoutService` verifies and finalizes completed checkouts.
- `PendingCheckoutFulfillmentService` routes success into order creation, extensions, booster tips, or admin tips.

### 💬 Chat Architecture

Each order receives three chat thread types:

| Thread | Participants |
| --- | --- |
| `customer_booster` | Customer and assigned booster |
| `customer_admin` | Customer and admin |
| `booster_admin` | Booster and admin |

Messages are fetched over authenticated HTTP routes and broadcast over private channels named:

```text
order-chat.{orderId}.{threadType}
```

---

## <a id="project-structure"></a>📁 Project Structure

| Path | Purpose |
| --- | --- |
| `app/Actions` | Transactional writes such as order creation, order claiming, completion, withdrawals, and admin updates |
| `app/Console/Commands` | Operational Artisan commands |
| `app/Contracts` | Payment, notification, and security abstractions |
| `app/Data` | Small data transfer objects and result objects |
| `app/Enums` | Typed values for chat thread types and customer email types |
| `app/Events` | Broadcast events such as order chat messages |
| `app/Http/Controllers` | Public, auth, customer, booster, admin, checkout, payment, chat, and media controllers |
| `app/Http/Middleware` | Role checks, admin checks, security headers, maintenance redirects, request logging context |
| `app/Http/Requests` | Validation and authorization rules by feature area |
| `app/Jobs` | Queued Discord and customer email dispatch jobs |
| `app/Mail` | Customer, booster, account lifecycle, and password reset mailables |
| `app/Models` | Users, orders, chats, payments, pricing, CMS, finance, OAuth, reviews, promotions, and audit logs |
| `app/Notifications` | Discord message payloads |
| `app/Observers` | Order lifecycle side effects |
| `app/Policies` | Admin-facing model authorization policies |
| `app/Queries` | Dashboard, listing, finance, public content, and social proof read models |
| `app/Services` | Payments, orders, chat, finance, mail, Discord, media, privacy, maintenance, settings, and security services |
| `app/Support` | Pricing, boosting catalog, agent catalog, CMS registry, permissions, logging, password policy, and helpers |
| `bootstrap/app.php` | Middleware, aliases, exception rendering, trusted proxies, broadcast auth, and app bootstrapping |
| `config` | App, admin, boosting, pricing, payments, broadcasting, websockets, startup, filesystems, mail, queue, logging |
| `database/migrations` | Schema for identity, orders, chat, payments, finance, CMS, pricing, notifications, queues, cache, and sessions |
| `database/seeders` | Demo users, platform content, add-on settings, pricing settings, blog/review/demo content |
| `public/assets` | Logo, favicon, social icons, add-on icons, and public image assets |
| `resources/css` | Application styles |
| `resources/js/src` | Checkout, estimator, pricing preview, chat, admin UI, agent selectors, contact form, and public social proof modules |
| `resources/views` | Blade pages, layouts, partials, admin/customer/booster views, emails, errors, and sitemap |
| `routes/web-routes` | Audience-specific web route files |
| `tests/Feature` | Feature coverage for payments, chat, auth, admin, pricing, CMS, finance, security, and public pages |
| `tests/Unit` | Unit coverage for pricing, startup validation, page titles, promo codes, and email types |

---

## <a id="roles-and-access"></a>👥 Roles and Access

### 🎭 Primary Roles

| Role | Area | Capabilities |
| --- | --- | --- |
| `customer` | `/user/*` | Place orders, view/manage own orders, use order chats, pause/resume eligible orders, extend orders, tip, update password/profile photo |
| `booster` | `/booster/*` | Claim orders, work assigned orders, update progress, upload proof, complete/drop orders, request withdrawals |
| `super_admin` | `/admin/*` | Access all configured admin modules and abilities |

### 🔐 Admin Model

The current admin model is intentionally simple:

- `role = super_admin` is the only active admin role.
- Legacy `admin` and `manager` values normalize to `super_admin`.
- `AdminPermission` exposes the ability list used by middleware, requests, policies, and admin navigation.
- Admin modules are listed in `config/admin.php`: dashboard, operations, people, marketing, content, finance, and system.
- Suspended admins are blocked before protected admin routes continue.
- The WebSockets dashboard gate is restricted to `super_admin`.

### 🚦 Access Rules Worth Preserving

- Customers can only see their own customer-visible order chats.
- Boosters can only see chats for orders assigned to them and still open in the booster workflow.
- Admins can view all order chat lanes.
- Sending rights follow the thread participants.
- Protected customer, booster, and admin routes block suspended users.

---

## <a id="core-workflows"></a>🔄 Core Workflows

### 1. 🔐 Account Lifecycle

1. Users register through the web signup form or authenticate through Google/Discord OAuth.
2. New local signups are created as `customer`.
3. OAuth users complete missing profile fields when needed.
4. Login attempts are rate-limited.
5. After repeated failures, the login flow requires a numeric CAPTCHA.
6. Suspended users are denied protected route access.
7. Password reset uses Laravel password tokens plus a queued transactional mail.
8. Account created, suspended, and reactivated emails are handled by the account lifecycle mail service.

### 2. 🧮 Storefront Pricing

1. The public UI captures service, rank, RR, region, platform, boost mode, add-ons, and agent choices.
2. The browser requests server-side calculations from `POST /calculate-price`.
3. The pricing engine validates the payload and calculates authoritative totals.
4. Promo preview calls `POST /checkout/promo-code/preview`.
5. The checkout submission is repriced again before payment initialization.

### 3. 💳 Checkout Finalization

1. The app stores a `pending_checkouts` record before redirecting to Stripe or Cryptomus.
2. The provider sends the user to a hosted payment experience.
3. The success page and/or webhook verifies provider state, checkout token, amount, and references.
4. `FinalizePendingCheckoutService` deduplicates and finalizes the checkout.
5. Fulfillment creates an order, applies an extension, or records a tip.
6. Promo codes are consumed only after successful finalization.
7. Order creation triggers chat-thread provisioning, email dispatch, and Discord notification dispatch.

### 4. 🧑‍💼 Admin Manual Orders

1. Admin selects a customer and optionally assigns a booster.
2. The manual order flow tries to build an authoritative pricing payload.
3. Admin can use a manual price override when the order intentionally bypasses storefront restrictions.
4. Assigned orders start `InProgress`; unassigned orders start `Pending`.
5. Booster assignment queues customer and booster assignment emails.

### 5. 🧑‍🚀 Booster Execution

1. Boosters view pending unassigned orders in the claim queue.
2. Claiming assigns the booster, sets assignment metadata, and moves the order to `InProgress`.
3. Boosters and admins update structured progress.
4. Customers can pause/resume eligible active orders.
5. Boosters can drop eligible active orders back to the queue.
6. Completion requires the assigned booster and an uploaded completion proof.

### 6. 💬 Order Communication

1. `EnsureOrderChatThreads` creates order chat lanes.
2. Message history is loaded over authenticated HTTP.
3. New messages broadcast over private Pusher-compatible channels.
4. System messages are posted for important events such as extensions.
5. Admins can monitor operational chat context from the admin panel.

### 7. 💸 Wallets and Withdrawals

1. Booster balances are derived from completed order payouts, wallet adjustments, booster tips, and withdrawals.
2. Boosters can request withdrawals only when funds are available.
3. Admins approve or reject withdrawal requests.
4. Approved withdrawals create wallet deductions and reconciliation metadata.
5. Booster withdrawal decision emails are queued after processing.

### 8. 📝 Content and Marketing

1. CMS page definitions live in `PageRegistry`.
2. Admin edits home, blog index, FAQ, contact, reviews, booster application, and legal page copy.
3. Admin manages FAQs, featured boosters, add-on tooltip overrides, reviews, promotions, promo codes, and blog articles.
4. Homepage content aggregates CMS sections, featured boosters, promotions, FAQs, social proof, and recent published blogs.
5. Public contact submissions and booster applications can dispatch Discord notifications.

---

## <a id="local-setup"></a>🚀 Local Setup

### ✅ Requirements

- PHP `8.2+`
- Composer
- Node.js and npm
- MySQL for the default local environment
- PHP extensions commonly required by Laravel plus uploads:
  - PDO driver for your database
  - Fileinfo
  - GD
  - EXIF recommended for JPEG orientation handling

The repository does not pin a Node engine in `package.json`; use a current Node LTS that is compatible with Vite 7.

### ⚡ Quick Start

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan storage:link
php artisan db:seed
composer dev
```

PowerShell equivalent for the environment file:

```powershell
Copy-Item .env.example .env
```

### 🧪 One-Command Setup Script

Composer also provides:

```bash
composer setup
```

That script installs dependencies, creates `.env` if missing, generates the key, runs migrations, installs Node dependencies, and builds frontend assets.

### 👤 Local Demo Users

In non-production environments, `php artisan db:seed` creates:

| Role | Email | Password |
| --- | --- | --- |
| 🛠️ Admin | `admin@ggwp.dev` | `Admin123!Secure` |
| 👤 Customer | `customer@ggwp.dev` | `Customer123!Secure` |

Production seeding refuses fixed demo credentials. If you intentionally seed a production admin, set strong `SEED_ADMIN_EMAIL` and `SEED_ADMIN_PASSWORD` values before running the seeder.

---

## <a id="environment-checklist"></a>⚙️ Environment Checklist

Start from `.env.example`, then configure the values that match your environment.

### 🧭 App and Runtime

| Variable Group | Purpose |
| --- | --- |
| `APP_NAME`, `APP_ENV`, `APP_KEY`, `APP_DEBUG`, `APP_URL` | Core Laravel runtime identity |
| `STARTUP_VALIDATION_ENABLED`, `STARTUP_VALIDATE_IN_CONSOLE` | Startup configuration validation |
| `TRUSTED_PROXIES` | Reverse proxy/load balancer trust settings |
| `VITE_APP_NAME` | Frontend app name exposed to Vite |

### 🗄️ Database, Cache, Queue, and Session

| Variable Group | Purpose |
| --- | --- |
| `DB_*` | Primary database connection |
| `SESSION_*` | Database-backed encrypted sessions by default |
| `CACHE_STORE`, `DB_CACHE_*` | Database cache and cache lock configuration |
| `QUEUE_CONNECTION`, `DB_QUEUE_*`, `QUEUE_FAILED_DRIVER` | Database queue, batches, and failed jobs |

### 💳 Payments

| Variable Group | Purpose |
| --- | --- |
| `STRIPE_ENABLED`, `STRIPE_KEY`, `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET` | Stripe Checkout and webhook verification |
| `CRYPTOMUS_ENABLED`, `CRYPTOMUS_MERCHANT_ID`, `CRYPTOMUS_API_KEY`, `CRYPTOMUS_BASE_URL`, `CRYPTOMUS_TIMEOUT`, `CRYPTOMUS_INVOICE_LIFETIME` | Cryptomus hosted crypto invoices |
| `PENDING_CHECKOUT_*` | Pending checkout TTL and retention windows |
| `WEBHOOK_PROCESSING_TIMEOUT_MINUTES` | Webhook processing retry timeout |
| `BOOSTER_PAYOUT_PERCENTAGE` | Default booster payout basis |

### 🌐 OAuth

| Variable Group | Purpose |
| --- | --- |
| `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI` | Google OAuth login |
| `DISCORD_CLIENT_ID`, `DISCORD_CLIENT_SECRET`, `DISCORD_REDIRECT_URI` | Discord OAuth login |

### ⚡ Realtime

| Variable Group | Purpose |
| --- | --- |
| `BROADCAST_CONNECTION=pusher` | Enables Pusher-compatible broadcasting |
| `PUSHER_*` | App credentials and host/port/scheme for Echo and WebSockets |
| `LARAVEL_WEBSOCKETS_*` | Self-hosted WebSockets server settings |
| `WEBSOCKETS_ALLOWED_ORIGINS` | Browser origin allowlist |

### 📣 Mail, Discord, and Support Links

| Variable Group | Purpose |
| --- | --- |
| `MAIL_*`, `MAIL_LOGO_URL` | Mail transport, sender, and branding |
| `CUSTOMER_ORDER_EMAILS_QUEUE`, `CUSTOMER_ORDER_EMAIL_RETRY_FAILED_AFTER_MINUTES`, `CUSTOMER_MAIL_LOG_LEVEL` | Customer order email queueing and retry behavior |
| `DISCORD_*_WEBHOOK_URL`, `DISCORD_NOTIFICATIONS_QUEUE`, `DISCORD_RETRY_FAILED_AFTER_MINUTES`, `DISCORD_WEBHOOK_*` | Discord notification destinations and retry behavior |
| `SUPPORT_EMAIL`, `SUPPORT_PHONE`, `COMMUNITY_DISCORD_URL` | Public support values |
| `SOCIAL_*_URL` | Public social links |
| `FOOTER_LEGAL_NAME`, `FOOTER_LEGAL_REGION` | Footer/legal display values |

### 📦 Storage and Logging

| Variable Group | Purpose |
| --- | --- |
| `FILESYSTEM_DISK` | Default filesystem disk; `.env.example` uses `public` |
| `AWS_*` | Optional S3-compatible storage |
| `LOG_*`, `PAYMENT_LOG_*`, `ACTIVITY_LOG_*`, `SECURITY_LOG_*`, `WEBHOOK_LOG_*`, `DISCORD_LOG_*` | General and domain-specific log channels |

### 🚨 Staging and Production Validation

In `staging` and `production`, startup validation is stricter. The app expects:

- valid `APP_KEY` and `APP_URL`
- coherent database, cache, queue, session, broadcast, and mail settings
- at least one fully configured payment provider
- required Stripe/Cryptomus credentials when those providers are enabled

If validation fails, the app throws during boot instead of running partially configured.

---

## <a id="running-the-app"></a>▶️ Running the App

### 🌟 Recommended Local Command

```bash
composer dev
```

This starts the Laravel server, WebSockets server, queue listener, log tail, and Vite dev server together through `concurrently`:

| Process | Command |
| --- | --- |
| 🌐 Web | `php artisan serve` |
| ⚡ WebSockets | `php artisan websockets:serve --host=127.0.0.1 --port=6001` |
| 📬 Queue | `php artisan queue:listen --tries=1 --timeout=0` |
| 📜 Logs | `php artisan pail --timeout=0` |
| 🎨 Assets | `npm run dev` |

### 🧩 Manual Split Commands

```bash
php artisan serve
```

```bash
php artisan websockets:serve --host=127.0.0.1 --port=6001
```

```bash
php artisan queue:listen --tries=1 --timeout=0
```

```bash
npm run dev
```

Optional log tail:

```bash
php artisan pail --timeout=0
```

### ⏱️ Scheduler

Local scheduler worker:

```bash
php artisan schedule:work
```

Production cron should run Laravel's scheduler every minute:

```bash
php artisan schedule:run
```

### ✅ Readiness Check

```text
GET /ready
```

The readiness endpoint checks:

- database connectivity
- cache write/read
- default filesystem write/read
- public storage symlink health

---

## <a id="testing"></a>🧪 Testing

Run the full suite:

```bash
composer test
```

or:

```bash
php artisan test
```

The test environment in `phpunit.xml` uses:

| Setting | Value |
| --- | --- |
| `DB_CONNECTION` | `sqlite` |
| `DB_DATABASE` | `:memory:` |
| `BROADCAST_CONNECTION` | `null` |
| `CACHE_STORE` | `array` |
| `MAIL_MAILER` | `array` |
| `QUEUE_CONNECTION` | `sync` |
| `SESSION_DRIVER` | `array` |

Coverage includes:

- 💳 Stripe and Cryptomus checkout/webhook/finalization behavior
- 🧮 pricing calculation and admin pricing settings
- 🏷️ promo code handling
- 💬 order chat and broadcast authorization
- 📈 order progress persistence
- ⬆️ extensions, pause/resume, and tip flows
- 🧑‍🚀 booster workspace rules and emails
- 🛠️ admin content, marketing, people, order, finance, pricing, and maintenance flows
- 🔐 login CAPTCHA, OAuth, role normalization, startup validation, and security hardening
- 🧾 finance reconciliation and payment identifier repair
- 🗺️ sitemap, public page rendering, Tawk widget behavior, profile photos, nicknames, and email templates

Because tests use in-memory SQLite and sync queues, they do not require real payment, mail, Discord, or WebSocket services.

---

## <a id="operations-commands"></a>🧰 Operations Commands

### 🛠️ Domain Commands

| Command | Purpose |
| --- | --- |
| `php artisan pending-checkouts:prune` | Delete stale and expired pending checkout records past retention |
| `php artisan discord:retry-dispatches` | Requeue stale or failed Discord notification dispatches |
| `php artisan finance:reconcile-withdrawals` | Backfill/reconcile historical approved withdrawals with wallet deductions |
| `php artisan privacy:redact-user-history {user_id} {--scope=all}` | Redact historical user identifiers from order text and chat data |
| `php artisan payments:repair-order-identifiers` | Repair legacy duplicate Stripe/payment identifiers before enforcing uniqueness |

### ⏲️ Scheduled Tasks

| Schedule | Command |
| --- | --- |
| Daily | `pending-checkouts:prune` |
| Every 10 minutes | `discord:retry-dispatches` |

### 🧹 Common Laravel Maintenance

```bash
php artisan optimize
php artisan optimize:clear
php artisan migrate --force
php artisan queue:work --queue=notifications,default
php artisan schedule:run
php artisan websockets:serve
vendor/bin/pint
npm run build
```

---

## <a id="payments"></a>💳 Payments

### 🟣 Supported Providers

| Provider | Flow |
| --- | --- |
| Stripe | Hosted Stripe Checkout |
| Cryptomus | Hosted crypto invoice |

### 🔁 High-Level Flow

1. The app stores checkout intent in `pending_checkouts`.
2. The selected provider creates a hosted payment experience.
3. The provider success route and/or webhook verifies payment state.
4. Finalization creates or updates the target business entity.
5. The pending checkout is marked completed and retained based on configured retention windows.

### 💜 Stripe Behavior

- Stores Stripe session IDs on pending checkout and final order metadata.
- Verifies checkout token, `payment_status=paid`, and amount totals.
- Handles success events such as `checkout.session.completed` and `checkout.session.async_payment_succeeded`.
- Uses `STRIPE_WEBHOOK_SECRET` for webhook signature verification.

### 🟧 Cryptomus Behavior

- Stores invoice UUID/order reference metadata on pending checkout records.
- Verifies paid status through Cryptomus API lookups and/or webhook payload data.
- Accepts paid states such as `paid` and `paid_over`.
- Verifies amount and order/reference consistency.

### 🛡️ Payment Safety

- Webhook events are persisted in `payment_webhook_events`.
- Processing-state locking reduces duplicate webhook work.
- Finalization is idempotent.
- Checkout token, provider reference, and amount checks protect fulfillment.
- Promo codes are consumed only after successful finalization.
- Free checkouts use an internal finalization path and synthetic payment reference.

---

## <a id="realtime-chat"></a>💬 Realtime Chat

### ⚡ Transport

- HTTP for initial history
- Private WebSocket channels for live messages
- Laravel Echo + Pusher protocol on the frontend
- `digiworld/laravel-websockets` as the default self-hosted server

### 🔐 Authorization

- Customers only access their own customer-visible threads.
- Boosters only access assigned-order threads while the order remains in an eligible booster lifecycle state.
- Admins can view all threads.
- Sending rights match thread participation rules.

### 🧭 Operational Notes

- Broadcast auth uses the `web` and `auth` middleware stack.
- Frontend broadcast configuration is exposed through `window.appState`.
- The WebSockets dashboard is gated to `super_admin`.
- Behind a proxy, align `APP_URL`, `PUSHER_*`, `TRUSTED_PROXIES`, and allowed origins.

---

## <a id="email-and-notifications"></a>📣 Email and Notifications

### ✉️ Customer Emails

Customer order emails are queued and deduplicated through `customer_order_email_dispatches`.

Supported customer order email types:

- order created
- order assigned
- order paused
- order cancelled
- order refunded
- order resumed
- order completed

### 🧑‍🚀 Booster Emails

Boosters receive transactional emails for:

- admin assignment to an order
- approved withdrawals
- rejected withdrawals

### 🔐 Account Lifecycle Emails

Users can receive:

- account created
- account suspended
- account reactivated
- password reset

### 💜 Discord Notifications

Discord dispatches are persisted and retried for:

- newly created orders
- booster applications
- contact form submissions
- withdrawal requests

Queue expectations:

- customer order emails default to the `notifications` queue
- Discord notifications default to the `notifications` queue
- queue workers must process `notifications,default` or these jobs will not move

### 💬 Public Support Widget

The public layout can load the Tawk.to widget on public-facing pages. Security headers allow Tawk sources only where the widget is expected and exclude admin, booster, and customer chat areas.

---

## <a id="security-notes"></a>🛡️ Security Notes

Important controls already in the codebase:

- 🔐 Role and admin-module middleware guard protected routes.
- 🚫 Suspended users are blocked from customer, booster, and admin areas.
- 🧩 Login is rate-limited and escalates to numeric CAPTCHA after repeated failures.
- 🚦 Dedicated rate limiters protect checkout, pricing, promo previews, chat, order progress, public forms, maintenance mode, withdrawals, OAuth, and webhooks.
- 🧱 Global security headers include CSP, frame denial, nosniff, referrer policy, permissions policy, COOP, CORP, and HSTS on secure requests.
- 🖼️ Image uploads are validated and re-encoded by media storage services.
- 🔏 Contact-message email fields and pending checkout/webhook payloads use encrypted model casting where configured by migrations/models.
- 💳 Payment webhooks verify signatures, amounts, references, and provider state before fulfillment.
- 🔁 Webhook idempotency and processing-state locking reduce duplicate processing risk.
- 🧼 Privacy tooling can redact historical user text from orders and chat.
- 🧾 Request IDs are bound into logging context for traceability.
- 🚧 Maintenance mode uses a custom system-setting flow with challenge token, exact phrase, CAPTCHA, and current admin password.

---

## <a id="deployment"></a>🚢 Deployment

### ✅ Production Prerequisites

- PHP `8.2+`
- Composer dependencies installed with optimized autoloading
- Built frontend assets
- Configured database
- Writable `storage` and `bootstrap/cache`
- Public storage symlink
- Queue worker processing at least `notifications,default`
- Scheduler running every minute
- WebSocket server process if live chat is enabled
- Stripe and/or Cryptomus fully configured in staging/production
- Correct `APP_URL`, proxy, broadcast, mail, and payment webhook settings

### 📦 Recommended Release Commands

```bash
composer install --no-dev --optimize-autoloader
npm install
npm run build
php artisan migrate --force
php artisan storage:link
php artisan optimize
```

### 🔄 Long-Running Processes

| Process | Command |
| --- | --- |
| 📬 Queue worker | `php artisan queue:work --queue=notifications,default` |
| ⏱️ Scheduler | `php artisan schedule:run` every minute through cron |
| ⚡ WebSockets | `php artisan websockets:serve` when realtime chat is enabled |

### 🌐 Web Server and Proxy Notes

- Set `APP_URL` correctly for checkout returns, signed links, password resets, and assets.
- Set `TRUSTED_PROXIES` when behind a load balancer or reverse proxy.
- Align `PUSHER_HOST`, `PUSHER_PORT`, `PUSHER_SCHEME`, and `WEBSOCKETS_ALLOWED_ORIGINS`.
- Keep webhook endpoints reachable during maintenance windows.
- Confirm `/ready` is healthy before shifting traffic.

### 📦 Storage Notes

- `php artisan storage:link` is expected for public disk assets.
- Profile photos can be served through signed routes.
- Promotion images are stored and served through dedicated media services.
- Completion proofs are stored on the `local` disk, rooted at `storage/app/private` in this project.

---

## <a id="troubleshooting"></a>🧯 Troubleshooting

### 🚨 The App Fails During Boot

Check:

- `APP_KEY`
- `APP_URL`
- database connection
- cache/session/queue driver configuration
- broadcast configuration
- mail transport settings
- Stripe/Cryptomus credentials in `staging` or `production`

### 🟡 `/ready` Returns `degraded`

Inspect which sub-check failed:

| Check | Likely Fix |
| --- | --- |
| `database` | Verify database host, credentials, database name, and network access |
| `cache` | Verify cache store and backing database/connection |
| `storage` | Ensure the configured disk is writable |
| `public_storage_link` | Run `php artisan storage:link` |

### 💬 Realtime Chat Is Not Updating

Check:

- `BROADCAST_CONNECTION=pusher`
- WebSockets server is running
- `PUSHER_*` values match backend and frontend expectations
- allowed origins include your current host
- the user is authenticated and authorized for the private channel
- the queue/log output for broadcast or auth failures

### 💳 Payments Are Not Finalizing

Check:

- provider is enabled and credentials are set
- webhook endpoint is publicly reachable
- webhook signatures are valid
- pending checkout exists and has not expired
- provider amount matches the expected amount
- success page verification has the expected provider reference
- queue workers are running for downstream notifications

### 🖼️ Media Uploads Fail

Check:

- PHP `gd` and `fileinfo` extensions are installed
- storage paths are writable
- upload MIME type and file extension are allowed
- uploaded files are valid `jpeg`, `png`, or `webp`

### 🗄️ SQLite Local State Gets Odd

If you switch from MySQL to SQLite locally, update the full stateful stack, not only `DB_CONNECTION`:

- `SESSION_CONNECTION`
- `DB_QUEUE_CONNECTION`
- `DB_QUEUE_BATCHING_CONNECTION`
- `DB_QUEUE_FAILED_CONNECTION`
- `DB_CACHE_CONNECTION`
- `DB_CACHE_LOCK_CONNECTION`

### 👤 Demo Users Are Missing

Migrations auto-seed platform content after migration commands, but demo users require:

```bash
php artisan db:seed
```

### 📣 Emails or Discord Notifications Are Stuck

Check:

- queue worker is running
- worker includes the `notifications` queue
- mail transport settings are valid
- Discord webhook URL for the notification type is configured
- retry command/schedule is running for Discord dispatches

---

## <a id="maintenance-notes"></a>🧑‍💻 Maintenance Notes

- Keep controllers thin; move complex writes into actions/services.
- Preserve the separation between actions, queries, services, support classes, and HTTP orchestration.
- Treat public JSON endpoints as frontend internals unless a formal API contract is created.
- Update `PageRegistry`, routes, views, admin editing, and tests together when adding CMS pages.
- Update pricing tests when changing `config/pricing.php`, pricing validation, or admin pricing settings.
- Run high-risk changes through targeted tests before merging.

### 🔥 High-Risk Areas

- payment finalization and idempotency
- promo code consumption
- pricing editor validation and fallback behavior
- wallet and withdrawal accounting
- chat authorization and broadcast configuration
- maintenance mode and admin authorization
- image upload validation and signed media delivery
- startup validation in staging/production

### ✅ Suggested Pre-Merge Checks

```bash
php artisan test
vendor/bin/pint
npm run build
```

Call out any migration, queue, env, websocket, webhook, pricing, or payment impact in release notes or PR descriptions.

---

## <a id="license"></a>📄 License

The repository metadata currently declares `MIT` in `composer.json`, but there is no top-level `LICENSE` file. Confirm the intended license with the maintainer before redistributing or publishing this project.
#   m u l t i g g w p b o o s t  
 