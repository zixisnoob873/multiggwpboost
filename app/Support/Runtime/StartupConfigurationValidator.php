<?php

namespace App\Support\Runtime;

use App\Services\Payments\PaymentManager;
use Illuminate\Contracts\Foundation\Application;
use RuntimeException;

class StartupConfigurationValidator
{
    protected const FORBIDDEN_BROADCAST_VALUES = [
        'ggwp-chat-app',
        'ggwp-chat-key',
        'ggwp-chat-secret',
        'local-chat-app',
        'local-chat-key',
        'local-change-me-secret',
    ];

    protected const FORBIDDEN_SECRET_VALUES = [
        'change-me',
        'changeme',
        'example',
        'key',
        'local-change-me-secret',
        'password',
        'placeholder',
        'replace-me',
        'secret',
        'test',
        'your-secret-here',
    ];

    public function __construct(
        protected Application $app,
        protected PaymentManager $paymentManager,
    ) {}

    public function validate(): void
    {
        if (! config('startup.enabled', true)) {
            return;
        }

        if ($this->app->environment(config('startup.skip_environments', ['testing']))) {
            return;
        }

        if ($this->app->runningInConsole() && ! config('startup.validate_in_console', false)) {
            return;
        }

        $errors = [];

        $this->validateApplication($errors);
        $this->validateDatabase($errors);
        $this->validateCache($errors);
        $this->validateSession($errors);
        $this->validateQueue($errors);
        $this->validateBroadcasting($errors);
        $this->validateMail($errors);
        $this->validatePayments($errors);
        $this->validateOAuth($errors);

        if ($errors === []) {
            return;
        }

        throw new RuntimeException(
            "Startup configuration validation failed:\n- ".implode("\n- ", $errors)
        );
    }

    /**
     * @param  array<int, string>  $errors
     */
    protected function validateApplication(array &$errors): void
    {
        $this->requireFilled($errors, 'app.key', config('app.key'));
        $this->requireValidUrl($errors, 'app.url', config('app.url'));

        if (! $this->productionHardeningValidationEnabled()) {
            return;
        }

        if ((bool) config('app.debug', false)) {
            $errors[] = 'app.debug must be false in production-like environments.';
        }

        if (parse_url((string) config('app.url'), PHP_URL_SCHEME) !== 'https') {
            $errors[] = 'app.url must use HTTPS in production-like environments.';
        }

        if ($this->appKeyLooksPlaceholder((string) config('app.key'))) {
            $errors[] = 'app.key must not use a placeholder value in production-like environments.';
        }

        if ((bool) config('startup.require_trusted_proxies', true) && blank(config('startup.trusted_proxies'))) {
            $errors[] = 'trusted proxies must be configured in production-like environments.';
        }
    }

    /**
     * @param  array<int, string>  $errors
     */
    protected function validateDatabase(array &$errors): void
    {
        $defaultConnection = (string) config('database.default', '');
        $connections = (array) config('database.connections', []);
        $connection = $this->databaseConnection($errors, $defaultConnection, $connections, 'database.default');

        if ($connection === null) {
            return;
        }

        $this->validateDatabaseConnectionDefinition($errors, $defaultConnection, $connection);
    }

    /**
     * @param  array<int, string>  $errors
     */
    protected function validateCache(array &$errors): void
    {
        $store = (string) config('cache.default', '');
        $stores = (array) config('cache.stores', []);
        $cacheStore = $stores[$store] ?? null;

        if (! is_array($cacheStore)) {
            $errors[] = "cache.default store [{$store}] is not configured.";

            return;
        }

        match ((string) ($cacheStore['driver'] ?? '')) {
            'database' => $this->validateNamedDatabaseConnection(
                $errors,
                (string) ($cacheStore['connection'] ?? ''),
                'cache.database.connection'
            ),
            'redis' => $this->validateNamedRedisConnection(
                $errors,
                (string) ($cacheStore['connection'] ?? 'default'),
                'cache.redis.connection'
            ),
            'memcached' => $this->requireFilled(
                $errors,
                'cache.memcached.servers.0.host',
                data_get($cacheStore, 'servers.0.host')
            ),
            'dynamodb' => $this->validateAwsBackedConfig($errors, 'cache.dynamodb'),
            default => null,
        };
    }

    /**
     * @param  array<int, string>  $errors
     */
    protected function validateSession(array &$errors): void
    {
        $driver = (string) config('session.driver', '');

        match ($driver) {
            'database' => $this->validateNamedDatabaseConnection(
                $errors,
                (string) config('session.connection', config('database.default')),
                'session.connection'
            ),
            'redis' => $this->validateNamedRedisConnection(
                $errors,
                (string) (config('session.store') ?: 'default'),
                'session.store'
            ),
            default => null,
        };

        if (! $this->productionHardeningValidationEnabled()) {
            return;
        }

        if (! (bool) config('session.secure')) {
            $errors[] = 'session.secure must be true in production-like environments.';
        }

        if (! (bool) config('session.http_only')) {
            $errors[] = 'session.http_only must be true.';
        }

        if (! (bool) config('session.encrypt')) {
            $errors[] = 'session.encrypt must be true.';
        }

        if (! in_array(config('session.same_site'), ['lax', 'strict'], true)) {
            $errors[] = 'session.same_site must be lax or strict.';
        }
    }

    /**
     * @param  array<int, string>  $errors
     */
    protected function validateQueue(array &$errors): void
    {
        $defaultConnection = (string) config('queue.default', '');
        $connections = (array) config('queue.connections', []);
        $queueConnection = $connections[$defaultConnection] ?? null;

        if (! is_array($queueConnection)) {
            $errors[] = "queue.default connection [{$defaultConnection}] is not configured.";

            return;
        }

        $this->validateQueueConnectionDefinition($errors, $defaultConnection, $queueConnection);
    }

    /**
     * @param  array<int, string>  $errors
     */
    protected function validateBroadcasting(array &$errors): void
    {
        $defaultConnection = (string) config('broadcasting.default', '');
        $connections = (array) config('broadcasting.connections', []);
        $broadcastConnection = $connections[$defaultConnection] ?? null;

        if (! is_array($broadcastConnection)) {
            $errors[] = "broadcasting.default connection [{$defaultConnection}] is not configured.";

            return;
        }

        match ((string) ($broadcastConnection['driver'] ?? '')) {
            'pusher' => [
                $this->requireFilled($errors, 'broadcasting.connections.pusher.key', $broadcastConnection['key'] ?? null),
                $this->requireFilled($errors, 'broadcasting.connections.pusher.secret', $broadcastConnection['secret'] ?? null),
                $this->requireFilled($errors, 'broadcasting.connections.pusher.app_id', $broadcastConnection['app_id'] ?? null),
                $this->requireFilled($errors, 'broadcasting.connections.pusher.options.host', data_get($broadcastConnection, 'options.host')),
                $this->validateProductionPusherConfig($errors, $broadcastConnection),
            ],
            'reverb' => [
                $this->requireFilled($errors, 'broadcasting.connections.reverb.key', $broadcastConnection['key'] ?? null),
                $this->requireFilled($errors, 'broadcasting.connections.reverb.secret', $broadcastConnection['secret'] ?? null),
                $this->requireFilled($errors, 'broadcasting.connections.reverb.app_id', $broadcastConnection['app_id'] ?? null),
                $this->requireFilled($errors, 'broadcasting.connections.reverb.options.host', data_get($broadcastConnection, 'options.host')),
            ],
            'ably' => $this->requireFilled($errors, 'broadcasting.connections.ably.key', $broadcastConnection['key'] ?? null),
            'redis' => $this->validateNamedRedisConnection($errors, 'default', 'broadcasting.redis.connection'),
            default => null,
        };
    }

    /**
     * @param  array<int, string>  $errors
     */
    protected function validateMail(array &$errors): void
    {
        $defaultMailer = (string) config('mail.default', '');
        $mailers = (array) config('mail.mailers', []);
        $mailer = $mailers[$defaultMailer] ?? null;

        if (! is_array($mailer)) {
            $errors[] = "mail.default mailer [{$defaultMailer}] is not configured.";

            return;
        }

        $fromAddress = (string) data_get(config('mail.from', []), 'address', '');

        if ($fromAddress !== '') {
            $this->requireValidEmail($errors, 'mail.from.address', $fromAddress);
        }

        match ((string) ($mailer['transport'] ?? '')) {
            'smtp' => [
                $this->requireFilled($errors, 'mail.mailers.smtp.host', $mailer['host'] ?? null),
                $this->requireFilled($errors, 'mail.mailers.smtp.port', $mailer['port'] ?? null),
            ],
            'ses' => $this->validateAwsBackedConfig($errors, 'mail.mailers.ses'),
            'postmark' => $this->requireFilled($errors, 'services.postmark.key', config('services.postmark.key')),
            'resend' => $this->requireFilled($errors, 'services.resend.key', config('services.resend.key')),
            default => null,
        };
    }

    /**
     * @param  array<int, string>  $errors
     */
    protected function validatePayments(array &$errors): void
    {
        if (! $this->strictIntegrationValidationEnabled()) {
            return;
        }

        if ((bool) config('services.stripe.enabled', true)) {
            $this->requireFilled($errors, 'services.stripe.key', config('services.stripe.key'));
            $this->requireFilled($errors, 'services.stripe.secret', config('services.stripe.secret'));
            $this->requireFilled($errors, 'services.stripe.webhook_secret', config('services.stripe.webhook_secret'));
            $this->requireNotPlaceholder($errors, 'services.stripe.key', config('services.stripe.key'));
            $this->requireNotPlaceholder($errors, 'services.stripe.secret', config('services.stripe.secret'));
            $this->requireNotPlaceholder($errors, 'services.stripe.webhook_secret', config('services.stripe.webhook_secret'));
        }

        if ((bool) config('services.cryptomus.enabled', true)) {
            $this->requireFilled($errors, 'services.cryptomus.merchant_id', config('services.cryptomus.merchant_id'));
            $this->requireFilled($errors, 'services.cryptomus.api_key', config('services.cryptomus.api_key'));
            $this->requireValidUrl($errors, 'services.cryptomus.base_url', config('services.cryptomus.base_url'));
            $this->requireNotPlaceholder($errors, 'services.cryptomus.merchant_id', config('services.cryptomus.merchant_id'));
            $this->requireNotPlaceholder($errors, 'services.cryptomus.api_key', config('services.cryptomus.api_key'));
            $this->validateCryptomusBaseUrl($errors);
        }

        if ($this->paymentManager->availableProviderKeys() === []) {
            $errors[] = 'At least one payment provider must be enabled and fully configured in strict integration environments.';
        }
    }

    protected function strictIntegrationValidationEnabled(): bool
    {
        return $this->app->environment(
            config('startup.strict_integration_environments', ['production'])
        );
    }

    protected function productionHardeningValidationEnabled(): bool
    {
        return $this->strictIntegrationValidationEnabled()
            && ! $this->app->environment('testing');
    }

    /**
     * @param  array<int, string>  $errors
     */
    protected function validateOAuth(array &$errors): void
    {
        if (! $this->productionHardeningValidationEnabled()) {
            return;
        }

        foreach (['google', 'discord'] as $provider) {
            $this->requireNotPlaceholder($errors, "services.{$provider}.client_id", config("services.{$provider}.client_id"));
            $this->requireNotPlaceholder($errors, "services.{$provider}.client_secret", config("services.{$provider}.client_secret"));

            $redirect = trim((string) config("services.{$provider}.redirect", ''));

            if ($redirect !== '' && ! str_starts_with($redirect, 'https://')) {
                $errors[] = "services.{$provider}.redirect must use HTTPS in production-like environments.";
            }
        }
    }

    /**
     * @param  array<int, string>  $errors
     */
    protected function validateCryptomusBaseUrl(array &$errors): void
    {
        if (! $this->productionHardeningValidationEnabled()) {
            return;
        }

        $parts = parse_url((string) config('services.cryptomus.base_url', ''));

        if (! is_array($parts)) {
            return;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = $this->normalizedHost((string) ($parts['host'] ?? ''));
        $allowedHosts = array_values(array_filter(array_map(
            fn (mixed $allowedHost): string => $this->normalizedHost((string) $allowedHost),
            (array) config('services.cryptomus.allowed_hosts', ['api.cryptomus.com'])
        )));

        if ($scheme !== 'https') {
            $errors[] = 'services.cryptomus.base_url must use HTTPS in production-like environments.';
        }

        if ($host === '' || ! in_array($host, $allowedHosts, true)) {
            $errors[] = 'services.cryptomus.base_url host must be allowlisted in production-like environments.';
        }
    }

    /**
     * @param  array<int, string>  $errors
     * @param  array<string, mixed>  $broadcastConnection
     */
    protected function validateProductionPusherConfig(array &$errors, array $broadcastConnection): void
    {
        if (! $this->productionHardeningValidationEnabled()) {
            return;
        }

        $this->requireNotIn(
            $errors,
            'broadcasting.connections.pusher.key',
            $broadcastConnection['key'] ?? null,
            self::FORBIDDEN_BROADCAST_VALUES
        );
        $this->requireNotIn(
            $errors,
            'broadcasting.connections.pusher.secret',
            $broadcastConnection['secret'] ?? null,
            self::FORBIDDEN_BROADCAST_VALUES
        );
        $this->requireNotIn(
            $errors,
            'broadcasting.connections.pusher.app_id',
            $broadcastConnection['app_id'] ?? null,
            self::FORBIDDEN_BROADCAST_VALUES
        );

        $allowedOrigins = (array) config('websockets.allowed_origins', []);
        if ($allowedOrigins === []) {
            $errors[] = 'websockets.allowed_origins must list the allowed production origins.';
        }

        $host = (string) data_get($broadcastConnection, 'options.host', '');
        $scheme = strtolower((string) data_get($broadcastConnection, 'options.scheme', ''));

        if ($scheme !== 'https') {
            $errors[] = 'broadcasting.connections.pusher.options.scheme must be https in production-like environments.';
        }

        if ($this->isLocalBrowserHost($host)) {
            $errors[] = 'broadcasting.connections.pusher.options.host must not be localhost or a loopback address in production-like environments.';
        }

        foreach ($allowedOrigins as $origin) {
            if (! $this->isHttpsPublicOrigin((string) $origin)) {
                $errors[] = 'websockets.allowed_origins must contain only HTTPS production origins.';

                break;
            }
        }
    }

    /**
     * @param  array<int, string>  $errors
     * @param  array<string, mixed>  $connections
     * @return array<string, mixed>|null
     */
    protected function databaseConnection(array &$errors, string $name, array $connections, string $label): ?array
    {
        if (! array_key_exists($name, $connections) || ! is_array($connections[$name])) {
            $errors[] = "{$label} [{$name}] is not configured.";

            return null;
        }

        return $connections[$name];
    }

    /**
     * @param  array<int, string>  $errors
     * @param  array<string, mixed>  $connection
     */
    protected function validateDatabaseConnectionDefinition(array &$errors, string $name, array $connection): void
    {
        $driver = (string) ($connection['driver'] ?? '');

        match ($driver) {
            'sqlite' => $this->requireFilled($errors, "database.connections.{$name}.database", $connection['database'] ?? null),
            'mysql', 'mariadb', 'pgsql', 'sqlsrv' => [
                $this->requireFilled($errors, "database.connections.{$name}.host", $connection['host'] ?? null),
                $this->requireFilled($errors, "database.connections.{$name}.port", $connection['port'] ?? null),
                $this->requireFilled($errors, "database.connections.{$name}.database", $connection['database'] ?? null),
                $this->requireFilled($errors, "database.connections.{$name}.username", $connection['username'] ?? null),
            ],
            default => $errors[] = "database.connections.{$name} uses an unsupported or missing driver.",
        };
    }

    /**
     * @param  array<int, string>  $errors
     */
    protected function validateNamedDatabaseConnection(array &$errors, string $name, string $label): void
    {
        $connection = $this->databaseConnection($errors, $name, (array) config('database.connections', []), $label);

        if ($connection !== null) {
            $this->validateDatabaseConnectionDefinition($errors, $name, $connection);
        }
    }

    /**
     * @param  array<int, string>  $errors
     */
    protected function validateNamedRedisConnection(array &$errors, string $name, string $label): void
    {
        $connections = (array) config('database.redis', []);
        $connection = $connections[$name] ?? null;

        if (! is_array($connection)) {
            $errors[] = "{$label} redis connection [{$name}] is not configured.";

            return;
        }

        if (blank($connection['url'] ?? null)) {
            $this->requireFilled($errors, "database.redis.{$name}.host", $connection['host'] ?? null);
            $this->requireFilled($errors, "database.redis.{$name}.port", $connection['port'] ?? null);
        }
    }

    /**
     * @param  array<int, string>  $errors
     * @param  array<string, mixed>  $connection
     */
    protected function validateQueueConnectionDefinition(array &$errors, string $name, array $connection): void
    {
        $driver = (string) ($connection['driver'] ?? '');

        match ($driver) {
            'database' => $this->validateNamedDatabaseConnection(
                $errors,
                (string) ($connection['connection'] ?? config('database.default')),
                "queue.connections.{$name}.connection"
            ),
            'redis' => $this->validateNamedRedisConnection(
                $errors,
                (string) ($connection['connection'] ?? 'default'),
                "queue.connections.{$name}.connection"
            ),
            'sqs' => [
                $this->requireFilled($errors, 'queue.connections.sqs.key', $connection['key'] ?? null),
                $this->requireFilled($errors, 'queue.connections.sqs.secret', $connection['secret'] ?? null),
                $this->requireFilled($errors, 'queue.connections.sqs.prefix', $connection['prefix'] ?? null),
                $this->requireFilled($errors, 'queue.connections.sqs.queue', $connection['queue'] ?? null),
                $this->requireFilled($errors, 'queue.connections.sqs.region', $connection['region'] ?? null),
            ],
            'beanstalkd' => $this->requireFilled($errors, 'queue.connections.beanstalkd.host', $connection['host'] ?? null),
            'failover' => $this->validateQueueFailoverConnections($errors, $connection),
            default => null,
        };
    }

    /**
     * @param  array<int, string>  $errors
     * @param  array<string, mixed>  $connection
     */
    protected function validateQueueFailoverConnections(array &$errors, array $connection): void
    {
        $fallbackConnections = array_values(array_filter((array) ($connection['connections'] ?? []), 'is_string'));

        if ($fallbackConnections === []) {
            $errors[] = 'queue.connections.failover.connections must contain at least one connection.';

            return;
        }

        foreach ($fallbackConnections as $fallbackConnection) {
            $configured = (array) config("queue.connections.{$fallbackConnection}", []);

            if ($configured === []) {
                $errors[] = "queue.connections.failover references missing connection [{$fallbackConnection}].";

                continue;
            }

            $this->validateQueueConnectionDefinition($errors, $fallbackConnection, $configured);
        }
    }

    /**
     * @param  array<int, string>  $errors
     */
    protected function validateAwsBackedConfig(array &$errors, string $context): void
    {
        $this->requireFilled($errors, 'services.ses.key', config('services.ses.key'));
        $this->requireFilled($errors, 'services.ses.secret', config('services.ses.secret'));
        $this->requireFilled($errors, 'services.ses.region', config('services.ses.region'));
    }

    /**
     * @param  array<int, string>  $errors
     */
    protected function requireFilled(array &$errors, string $label, mixed $value): void
    {
        if (filled($value)) {
            return;
        }

        $errors[] = "{$label} is required.";
    }

    /**
     * @param  array<int, string>  $errors
     * @param  array<int, string>  $forbidden
     */
    protected function requireNotIn(array &$errors, string $label, mixed $value, array $forbidden): void
    {
        if (! in_array((string) $value, $forbidden, true)) {
            return;
        }

        $errors[] = "{$label} must not use local or default demo credentials.";
    }

    /**
     * @param  array<int, string>  $errors
     */
    protected function requireNotPlaceholder(array &$errors, string $label, mixed $value): void
    {
        $normalized = strtolower(trim((string) $value));

        if ($normalized === '') {
            return;
        }

        if (
            in_array($normalized, self::FORBIDDEN_SECRET_VALUES, true)
            || str_contains($normalized, 'change-me')
            || str_contains($normalized, 'placeholder')
            || str_starts_with($normalized, 'replace_with')
            || str_starts_with($normalized, 'replace-')
            || str_starts_with($normalized, 'sk_test_')
        ) {
            $errors[] = "{$label} must not use local, test, or placeholder credentials.";
        }
    }

    /**
     * @param  array<int, string>  $errors
     */
    protected function requireValidUrl(array &$errors, string $label, mixed $value): void
    {
        if (filled($value) && filter_var((string) $value, FILTER_VALIDATE_URL)) {
            return;
        }

        $errors[] = "{$label} must be a valid URL.";
    }

    /**
     * @param  array<int, string>  $errors
     */
    protected function requireValidEmail(array &$errors, string $label, mixed $value): void
    {
        if (filled($value) && filter_var((string) $value, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $errors[] = "{$label} must be a valid email address.";
    }

    protected function isHttpsPublicOrigin(string $origin): bool
    {
        $parts = parse_url($origin);

        if (! is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https') {
            return false;
        }

        return ! $this->isLocalBrowserHost((string) ($parts['host'] ?? ''));
    }

    protected function isLocalBrowserHost(string $host): bool
    {
        $normalizedHost = strtolower(trim($host, "[] \t\n\r\0\x0B."));

        return in_array($normalizedHost, ['localhost', '127.0.0.1', '::1', '0.0.0.0'], true);
    }

    protected function appKeyLooksPlaceholder(string $key): bool
    {
        $normalized = strtolower(trim($key));

        return $normalized === ''
            || str_contains($normalized, 'change-me')
            || str_contains($normalized, 'placeholder')
            || str_contains($normalized, 'replace')
            || $normalized === 'base64:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa=';
    }

    protected function normalizedHost(string $host): string
    {
        return strtolower(trim($host, "[] \t\n\r\0\x0B."));
    }
}
