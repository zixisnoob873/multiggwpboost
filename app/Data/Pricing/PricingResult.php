<?php

namespace App\Data\Pricing;

use Illuminate\Validation\ValidationException;

final readonly class PricingResult
{
    public function __construct(
        public PricingRequest $request,
        public array $payload,
        public float $finalPrice,
        public int $finalPriceCents,
        public array $validationErrors,
    ) {}

    public static function fromPayload(PricingRequest $request, array $payload): self
    {
        $finalPrice = self::roundMoney((float) data_get($payload, 'pricing.total', $payload['finalPrice'] ?? 0));

        return new self(
            request: $request,
            payload: $payload,
            finalPrice: $finalPrice,
            finalPriceCents: max(0, (int) round($finalPrice * 100)),
            validationErrors: (array) ($payload['validationErrors'] ?? []),
        );
    }

    public function hasValidationErrors(): bool
    {
        return $this->validationErrors !== [];
    }

    public function throwIfInvalid(): self
    {
        if ($this->hasValidationErrors()) {
            throw ValidationException::withMessages($this->validationErrors);
        }

        return $this;
    }

    public function toArray(): array
    {
        $payload = $this->payload;
        $payload['finalPriceCents'] = $this->finalPriceCents;
        $payload['pricing'] = array_merge((array) ($payload['pricing'] ?? []), [
            'totalCents' => $this->finalPriceCents,
            'finalPriceCents' => $this->finalPriceCents,
        ]);

        return $payload;
    }

    public function pricingEvidence(): array
    {
        return [
            'input' => $this->request->evidenceInput(),
            'basePrice' => self::roundMoney((float) ($this->payload['basePrice'] ?? 0)),
            'finalPrice' => $this->finalPrice,
            'finalPriceCents' => $this->finalPriceCents,
            'currency' => (string) data_get($this->payload, 'pricing.currency', 'USD'),
            'rankPath' => array_values((array) ($this->payload['rankPath'] ?? [])),
            'addonBreakdown' => array_values((array) ($this->payload['addonBreakdown'] ?? [])),
            'modifiers' => (array) ($this->payload['modifiers'] ?? []),
            'pricingConfig' => (array) ($this->payload['pricingConfig'] ?? []),
            'validationErrors' => $this->validationErrors,
            'calculatedAt' => now()->toIso8601String(),
        ];
    }

    protected static function roundMoney(float $value): float
    {
        return round($value + 0.0000001, 2);
    }
}
