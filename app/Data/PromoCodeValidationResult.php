<?php

namespace App\Data;

use App\Models\PromoCode;

class PromoCodeValidationResult
{
    public function __construct(
        public readonly bool $valid,
        public readonly ?PromoCode $promoCode = null,
        public readonly ?string $normalizedCode = null,
        public readonly float $orderAmount = 0.0,
        public readonly float $discountAmount = 0.0,
        public readonly float $discountedTotal = 0.0,
        public readonly array $errors = [],
        public readonly array $validationErrors = [],
        public readonly array $originalOrderPayload = [],
        public readonly array $resolvedOrderPayload = [],
        public readonly array $promoAddonAdjustments = [],
        public readonly array $promoManagedAddons = [],
        public readonly array $promoAddedAddons = [],
    ) {}

    public function firstError(): string
    {
        return $this->errors[0] ?? 'This promo code could not be applied.';
    }

    public function toArray(): array
    {
        return [
            'valid' => $this->valid,
            'code' => $this->promoCode?->code ?? $this->normalizedCode,
            'promoCodeId' => $this->promoCode?->id,
            'type' => $this->promoCode?->type,
            'typeLabel' => $this->promoCode?->typeLabel(),
            'value' => $this->promoCode ? (float) $this->promoCode->value : null,
            'displayValue' => $this->promoCode?->displayValue(),
            'orderAmount' => round($this->orderAmount, 2),
            'discountAmount' => round($this->discountAmount, 2),
            'discountedTotal' => round($this->discountedTotal, 2),
            'errors' => $this->errors,
            'validationErrors' => $this->validationErrors,
            'promoAddonAdjustments' => $this->promoAddonAdjustments,
            'promoManagedAddons' => $this->promoManagedAddons,
            'promoAddedAddons' => $this->promoAddedAddons,
        ];
    }
}
