<?php

namespace App\Data\Payments;

use Illuminate\Support\Str;

class PendingCheckout
{
    public function __construct(
        public readonly string $token,
        public readonly string $reference,
        public readonly int $userId,
        public readonly array $requestData,
        public readonly array $orderPayload,
        public readonly string $paymentMethod,
        public readonly int $priceCents,
        public readonly float $total,
        public readonly float $subtotal,
        public readonly ?int $promoCodeId = null,
        public readonly ?string $promoCode = null,
        public readonly float $discountAmount = 0.0,
        public readonly array $baseOrderPayload = [],
        public readonly array $metadata = [],
        public readonly ?int $storedId = null,
        public readonly ?string $expiresAt = null,
        public readonly ?int $completedOrderId = null,
    ) {}

    public static function fromCheckoutData(int $userId, PaymentCheckoutData $checkoutData): self
    {
        return new self(
            token: (string) Str::uuid(),
            reference: 'CHK-'.Str::upper(Str::random(8)),
            userId: $userId,
            requestData: $checkoutData->requestData,
            orderPayload: $checkoutData->orderPayload,
            paymentMethod: $checkoutData->paymentMethod,
            priceCents: $checkoutData->priceCents,
            total: $checkoutData->total,
            subtotal: $checkoutData->subtotal,
            promoCodeId: $checkoutData->promoCodeId,
            promoCode: $checkoutData->promoCode,
            discountAmount: $checkoutData->discountAmount,
            baseOrderPayload: $checkoutData->baseOrderPayload,
            metadata: $checkoutData->metadata,
        );
    }

    public static function fromArray(array $payload): self
    {
        return new self(
            token: (string) ($payload['token'] ?? ''),
            reference: (string) ($payload['reference'] ?? ''),
            userId: (int) ($payload['userId'] ?? 0),
            requestData: (array) ($payload['requestData'] ?? []),
            orderPayload: (array) ($payload['orderPayload'] ?? []),
            paymentMethod: (string) ($payload['paymentMethod'] ?? 'stripe'),
            priceCents: (int) ($payload['priceCents'] ?? 0),
            total: (float) ($payload['total'] ?? 0),
            subtotal: (float) ($payload['subtotal'] ?? $payload['total'] ?? 0),
            promoCodeId: isset($payload['promoCodeId']) ? (int) $payload['promoCodeId'] : null,
            promoCode: isset($payload['promoCode']) ? (string) $payload['promoCode'] : null,
            discountAmount: (float) ($payload['discountAmount'] ?? 0),
            baseOrderPayload: (array) ($payload['baseOrderPayload'] ?? []),
            metadata: (array) ($payload['metadata'] ?? []),
            storedId: isset($payload['storedId']) ? (int) $payload['storedId'] : null,
            expiresAt: isset($payload['expiresAt']) ? (string) $payload['expiresAt'] : null,
            completedOrderId: isset($payload['completedOrderId']) ? (int) $payload['completedOrderId'] : null,
        );
    }

    public function toArray(): array
    {
        return [
            'token' => $this->token,
            'reference' => $this->reference,
            'userId' => $this->userId,
            'requestData' => $this->requestData,
            'orderPayload' => $this->orderPayload,
            'paymentMethod' => $this->paymentMethod,
            'priceCents' => $this->priceCents,
            'total' => $this->total,
            'subtotal' => $this->subtotal,
            'promoCodeId' => $this->promoCodeId,
            'promoCode' => $this->promoCode,
            'discountAmount' => $this->discountAmount,
            'baseOrderPayload' => $this->baseOrderPayload,
            'metadata' => $this->metadata,
            'storedId' => $this->storedId,
            'expiresAt' => $this->expiresAt,
            'completedOrderId' => $this->completedOrderId,
        ];
    }

    public function with(array $attributes = []): self
    {
        return new self(
            token: $attributes['token'] ?? $this->token,
            reference: $attributes['reference'] ?? $this->reference,
            userId: $attributes['userId'] ?? $this->userId,
            requestData: $attributes['requestData'] ?? $this->requestData,
            orderPayload: $attributes['orderPayload'] ?? $this->orderPayload,
            paymentMethod: $attributes['paymentMethod'] ?? $this->paymentMethod,
            priceCents: $attributes['priceCents'] ?? $this->priceCents,
            total: $attributes['total'] ?? $this->total,
            subtotal: $attributes['subtotal'] ?? $this->subtotal,
            promoCodeId: array_key_exists('promoCodeId', $attributes) ? $attributes['promoCodeId'] : $this->promoCodeId,
            promoCode: array_key_exists('promoCode', $attributes) ? $attributes['promoCode'] : $this->promoCode,
            discountAmount: $attributes['discountAmount'] ?? $this->discountAmount,
            baseOrderPayload: $attributes['baseOrderPayload'] ?? $this->baseOrderPayload,
            metadata: $attributes['metadata'] ?? $this->metadata,
            storedId: array_key_exists('storedId', $attributes) ? $attributes['storedId'] : $this->storedId,
            expiresAt: array_key_exists('expiresAt', $attributes) ? $attributes['expiresAt'] : $this->expiresAt,
            completedOrderId: array_key_exists('completedOrderId', $attributes) ? $attributes['completedOrderId'] : $this->completedOrderId,
        );
    }

    public function withMergedMetadata(array $metadata): self
    {
        return $this->with([
            'metadata' => array_merge($this->metadata, $metadata),
        ]);
    }

    public function toCheckoutData(?string $paymentMethod = null): PaymentCheckoutData
    {
        return new PaymentCheckoutData(
            requestData: $this->requestData,
            orderPayload: $this->orderPayload,
            paymentMethod: $paymentMethod ?? $this->paymentMethod,
            priceCents: $this->priceCents,
            total: $this->total,
            subtotal: $this->subtotal,
            promoCodeId: $this->promoCodeId,
            promoCode: $this->promoCode,
            discountAmount: $this->discountAmount,
            baseOrderPayload: $this->baseOrderPayload,
            metadata: $this->metadata,
        );
    }
}
