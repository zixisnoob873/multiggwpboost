<?php

namespace Tests\Unit;

use App\Support\Runtime\StartupConfigurationValidator;
use RuntimeException;
use Tests\TestCase;

class StartupConfigurationValidatorTest extends TestCase
{
    public function test_it_throws_when_the_app_key_is_missing(): void
    {
        config()->set('startup.skip_environments', []);
        config()->set('startup.validate_in_console', true);
        config()->set('startup.strict_integration_environments', ['production']);
        config()->set('app.key', null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('app.key is required.');

        app(StartupConfigurationValidator::class)->validate();
    }

    public function test_it_requires_configured_payments_in_strict_integration_environments(): void
    {
        config()->set('startup.skip_environments', []);
        config()->set('startup.validate_in_console', true);
        config()->set('startup.strict_integration_environments', ['testing']);
        config()->set('services.stripe.enabled', true);
        config()->set('services.stripe.key', null);
        config()->set('services.stripe.secret', null);
        config()->set('services.stripe.webhook_secret', null);
        config()->set('services.cryptomus.enabled', false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('services.stripe.key is required.');

        app(StartupConfigurationValidator::class)->validate();
    }

    public function test_it_rejects_default_websocket_credentials_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        $this->configureValidProductionBaseline();
        config()->set('broadcasting.default', 'pusher');
        config()->set('broadcasting.connections.pusher.key', 'local-chat-key');
        config()->set('broadcasting.connections.pusher.secret', 'local-change-me-secret');
        config()->set('broadcasting.connections.pusher.app_id', 'local-chat-app');
        config()->set('broadcasting.connections.pusher.options.host', 'ws.ggwp.example');
        config()->set('broadcasting.connections.pusher.options.scheme', 'https');
        config()->set('websockets.allowed_origins', ['https://ggwp.example']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('must not use local or default demo credentials');

        app(StartupConfigurationValidator::class)->validate();
    }

    public function test_it_requires_websocket_allowed_origins_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        $this->configureValidProductionBaseline();
        config()->set('broadcasting.default', 'pusher');
        config()->set('broadcasting.connections.pusher.key', 'prod-key');
        config()->set('broadcasting.connections.pusher.secret', 'prod-secret');
        config()->set('broadcasting.connections.pusher.app_id', 'prod-app');
        config()->set('broadcasting.connections.pusher.options.host', 'ws.ggwp.example');
        config()->set('broadcasting.connections.pusher.options.scheme', 'https');
        config()->set('websockets.allowed_origins', []);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('websockets.allowed_origins must list the allowed production origins.');

        app(StartupConfigurationValidator::class)->validate();
    }

    public function test_it_rejects_insecure_production_browser_and_session_settings(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        $this->configureValidProductionBaseline();
        config()->set('app.debug', true);
        config()->set('app.url', 'http://ggwp.example');
        config()->set('session.secure', false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('app.debug must be false');

        app(StartupConfigurationValidator::class)->validate();
    }

    public function test_it_rejects_http_app_url_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        $this->configureValidProductionBaseline();
        config()->set('app.url', 'http://ggwp.example');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('app.url must use HTTPS');

        app(StartupConfigurationValidator::class)->validate();
    }

    public function test_it_rejects_insecure_session_cookie_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        $this->configureValidProductionBaseline();
        config()->set('session.secure', false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('session.secure must be true');

        app(StartupConfigurationValidator::class)->validate();
    }

    public function test_it_rejects_placeholder_payment_secrets_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        $this->configureValidProductionBaseline();
        config()->set('services.stripe.secret', 'local-change-me-secret');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('services.stripe.secret must not use local, test, or placeholder credentials');

        app(StartupConfigurationValidator::class)->validate();
    }

    public function test_it_rejects_disallowed_cryptomus_hosts_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        $this->configureValidProductionBaseline();
        config()->set('services.cryptomus.enabled', true);
        config()->set('services.cryptomus.merchant_id', 'cryptomus-merchant-valid');
        config()->set('services.cryptomus.api_key', 'cryptomus-api-valid');
        config()->set('services.cryptomus.base_url', 'https://evil.example');
        config()->set('services.cryptomus.allowed_hosts', ['api.cryptomus.com']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('services.cryptomus.base_url host must be allowlisted');

        app(StartupConfigurationValidator::class)->validate();
    }

    public function test_it_rejects_insecure_cryptomus_scheme_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        $this->configureValidProductionBaseline();
        config()->set('services.cryptomus.enabled', true);
        config()->set('services.cryptomus.merchant_id', 'cryptomus-merchant-valid');
        config()->set('services.cryptomus.api_key', 'cryptomus-api-valid');
        config()->set('services.cryptomus.base_url', 'http://api.cryptomus.com');
        config()->set('services.cryptomus.allowed_hosts', ['api.cryptomus.com']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('services.cryptomus.base_url must use HTTPS');

        app(StartupConfigurationValidator::class)->validate();
    }

    public function test_it_accepts_allowlisted_cryptomus_base_url_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        $this->configureValidProductionBaseline();
        config()->set('services.cryptomus.enabled', true);
        config()->set('services.cryptomus.merchant_id', 'cryptomus-merchant-valid');
        config()->set('services.cryptomus.api_key', 'cryptomus-api-valid');
        config()->set('services.cryptomus.base_url', 'https://api.cryptomus.com');
        config()->set('services.cryptomus.allowed_hosts', ['api.cryptomus.com']);

        app(StartupConfigurationValidator::class)->validate();

        $this->assertTrue(true);
    }

    public function test_it_rejects_local_or_insecure_websocket_browser_endpoints_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        $this->configureValidProductionBaseline();
        config()->set('broadcasting.default', 'pusher');
        config()->set('broadcasting.connections.pusher.key', 'prod-key');
        config()->set('broadcasting.connections.pusher.secret', 'prod-secret');
        config()->set('broadcasting.connections.pusher.app_id', 'prod-app');
        config()->set('broadcasting.connections.pusher.options.host', '127.0.0.1');
        config()->set('broadcasting.connections.pusher.options.scheme', 'http');
        config()->set('websockets.allowed_origins', ['http://localhost']);

        try {
            app(StartupConfigurationValidator::class)->validate();
            $this->fail('Expected production websocket validation to fail.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('broadcasting.connections.pusher.options.scheme must be https', $exception->getMessage());
            $this->assertStringContainsString('broadcasting.connections.pusher.options.host must not be localhost', $exception->getMessage());
            $this->assertStringContainsString('websockets.allowed_origins must contain only HTTPS production origins', $exception->getMessage());
        }
    }

    protected function configureValidProductionBaseline(): void
    {
        config()->set('startup.skip_environments', []);
        config()->set('startup.validate_in_console', true);
        config()->set('startup.strict_integration_environments', ['production']);
        config()->set('app.key', 'base64:m6uO6d0qzqPZ4LxNRtyr1VdPJ47pJas9s8J1LYOU9u4=');
        config()->set('app.debug', false);
        config()->set('app.url', 'https://ggwp.example');
        config()->set('startup.require_trusted_proxies', true);
        config()->set('startup.trusted_proxies', '10.0.0.0/8');
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        config()->set('cache.default', 'array');
        config()->set('session.driver', 'array');
        config()->set('session.secure', true);
        config()->set('session.http_only', true);
        config()->set('session.encrypt', true);
        config()->set('session.same_site', 'lax');
        config()->set('queue.default', 'sync');
        config()->set('broadcasting.default', 'log');
        config()->set('mail.default', 'array');
        config()->set('services.stripe.enabled', true);
        config()->set('services.stripe.key', 'stripe-publishable-valid');
        config()->set('services.stripe.secret', 'stripe-secret-valid');
        config()->set('services.stripe.webhook_secret', 'stripe-webhook-valid');
        config()->set('services.cryptomus.enabled', false);
        config()->set('services.google.redirect', 'https://ggwp.example/auth/google/callback');
        config()->set('services.discord.redirect', 'https://ggwp.example/auth/discord/callback');
    }
}
