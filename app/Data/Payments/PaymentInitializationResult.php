<?php

namespace App\Data\Payments;

class PaymentInitializationResult
{
    public function __construct(
        public readonly string $type,
        public readonly string $target,
        public readonly array $metadata = []
    ) {}

    public static function redirect(string $url, array $metadata = []): self
    {
        return new self('redirect', $url, $metadata);
    }

    public static function route(string $routeName, array $parameters = [], array $metadata = []): self
    {
        return new self('route', $routeName, [
            'parameters' => $parameters,
            ...$metadata,
        ]);
    }

    public static function handoff(string $provider, array $metadata = []): self
    {
        return new self('handoff', $provider, [
            'provider' => $provider,
            ...$metadata,
        ]);
    }
}
