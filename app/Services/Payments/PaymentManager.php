<?php

namespace App\Services\Payments;

use App\Contracts\Payments\PaymentProvider;
use App\Data\Payments\PaymentProviderDescriptor;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class PaymentManager
{
    protected Collection $providers;

    /**
     * @param  iterable<PaymentProvider>  $providers
     */
    public function __construct(iterable $providers)
    {
        $this->providers = collect($providers)->keyBy(fn (PaymentProvider $provider) => $provider->key());
    }

    public function allDescriptors(): array
    {
        return $this->providers
            ->map(fn (PaymentProvider $provider) => $provider->descriptor()->toArray())
            ->values()
            ->all();
    }

    public function availableProviderKeys(): array
    {
        return $this->providers
            ->filter(function (PaymentProvider $provider): bool {
                $descriptor = $provider->descriptor();

                return $descriptor->isAvailable && $descriptor->isConfigured;
            })
            ->keys()
            ->values()
            ->all();
    }

    public function providerKeys(): array
    {
        return $this->providers
            ->keys()
            ->values()
            ->all();
    }

    public function defaultProvider(): PaymentProviderDescriptor
    {
        $descriptors = $this->providers
            ->map(fn (PaymentProvider $provider) => $provider->descriptor())
            ->values();

        $default = $descriptors->first(fn (PaymentProviderDescriptor $descriptor) => $descriptor->isDefault && $descriptor->isAvailable && $descriptor->isConfigured);

        if ($default) {
            return $default;
        }

        $available = $descriptors->first(fn (PaymentProviderDescriptor $descriptor) => $descriptor->isAvailable && $descriptor->isConfigured);

        return $available
            ?? $descriptors->first(fn (PaymentProviderDescriptor $descriptor) => $descriptor->isAvailable)
            ?? $descriptors->first();
    }

    public function provider(string $key): PaymentProvider
    {
        $provider = $this->providers->get($key);

        if (! $provider) {
            throw new InvalidArgumentException("Unsupported payment provider [{$key}].");
        }

        return $provider;
    }
}
