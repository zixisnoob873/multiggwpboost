<?php

namespace App\Services\Payments;

use App\Data\Payments\PaymentCheckoutData;
use App\Data\Payments\PendingCheckout;
use App\Models\Order;
use App\Models\PendingCheckoutRecord;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class PendingCheckoutStore
{
    public function create(int $userId, PaymentCheckoutData $checkoutData): PendingCheckout
    {
        $pendingCheckout = PendingCheckout::fromCheckoutData($userId, $checkoutData);
        $expiresAt = now()->addHours($this->ttlHours());

        $record = PendingCheckoutRecord::query()->create([
            'token' => $pendingCheckout->token,
            'reference' => $pendingCheckout->reference,
            'user_id' => $pendingCheckout->userId,
            'payment_method' => $pendingCheckout->paymentMethod,
            'price_cents' => $pendingCheckout->priceCents,
            'total' => $pendingCheckout->total,
            'subtotal' => $pendingCheckout->subtotal,
            'promo_code_id' => $pendingCheckout->promoCodeId,
            'promo_code' => $pendingCheckout->promoCode,
            'discount_amount' => $pendingCheckout->discountAmount,
            'request_data' => $pendingCheckout->requestData,
            'order_payload' => $pendingCheckout->orderPayload,
            'base_order_payload' => $pendingCheckout->baseOrderPayload,
            'metadata' => $pendingCheckout->metadata,
            'expires_at' => $expiresAt,
        ]);

        return $this->toData($record);
    }

    public function find(string $token): ?PendingCheckout
    {
        $record = PendingCheckoutRecord::query()
            ->where('token', $token)
            ->first();

        return $record ? $this->toData($record) : null;
    }

    public function findActive(string $token): ?PendingCheckout
    {
        $record = PendingCheckoutRecord::query()
            ->where('token', $token)
            ->first();

        if (! $record) {
            return null;
        }

        if ($record->completed_order_id === null && $record->expires_at && $record->expires_at->isPast()) {
            return null;
        }

        return $this->toData($record);
    }

    public function recordForToken(string $token, bool $lockForUpdate = false): ?PendingCheckoutRecord
    {
        $query = PendingCheckoutRecord::query()->where('token', $token);

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    public function findByReference(string $reference): ?PendingCheckout
    {
        $record = PendingCheckoutRecord::query()
            ->where('reference', $reference)
            ->first();

        return $record ? $this->toData($record) : null;
    }

    public function recordForReference(string $reference, bool $lockForUpdate = false): ?PendingCheckoutRecord
    {
        $query = PendingCheckoutRecord::query()->where('reference', $reference);

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    public function put(PendingCheckout $pendingCheckout): PendingCheckout
    {
        $record = $pendingCheckout->storedId
            ? PendingCheckoutRecord::query()->find($pendingCheckout->storedId)
            : PendingCheckoutRecord::query()->where('token', $pendingCheckout->token)->first();

        if (! $record) {
            $record = new PendingCheckoutRecord([
                'token' => $pendingCheckout->token,
                'reference' => $pendingCheckout->reference,
                'expires_at' => now()->addHours($this->ttlHours()),
            ]);
        }

        $record->forceFill([
            'token' => $pendingCheckout->token,
            'reference' => $pendingCheckout->reference,
            'user_id' => $pendingCheckout->userId,
            'payment_method' => $pendingCheckout->paymentMethod,
            'price_cents' => $pendingCheckout->priceCents,
            'total' => $pendingCheckout->total,
            'subtotal' => $pendingCheckout->subtotal,
            'promo_code_id' => $pendingCheckout->promoCodeId,
            'promo_code' => $pendingCheckout->promoCode,
            'discount_amount' => $pendingCheckout->discountAmount,
            'request_data' => $pendingCheckout->requestData,
            'order_payload' => $pendingCheckout->orderPayload,
            'base_order_payload' => $pendingCheckout->baseOrderPayload,
            'metadata' => $pendingCheckout->metadata,
            'completed_order_id' => $pendingCheckout->completedOrderId,
            'expires_at' => $pendingCheckout->expiresAt ? Carbon::parse($pendingCheckout->expiresAt) : ($record->expires_at ?? now()->addHours($this->ttlHours())),
        ])->save();

        return $this->toData($record->refresh());
    }

    public function markCompleted(PendingCheckout $pendingCheckout, Order $order): PendingCheckout
    {
        $record = $this->recordForToken($pendingCheckout->token, true);

        if (! $record) {
            return $pendingCheckout->with([
                'completedOrderId' => $order->id,
                'metadata' => array_merge($pendingCheckout->metadata, [
                    'completedOrderId' => $order->id,
                ]),
            ]);
        }

        $metadata = array_merge(is_array($record->metadata) ? $record->metadata : [], $pendingCheckout->metadata, [
            'completedOrderId' => $order->id,
        ]);

        $record->forceFill([
            'metadata' => $metadata,
            'completed_order_id' => $order->id,
            'finalized_at' => now(),
        ])->save();

        return $this->toData($record->refresh());
    }

    public function forget(string $token): void
    {
        PendingCheckoutRecord::query()
            ->where('token', $token)
            ->delete();
    }

    public function staleRecordsQuery(): Builder
    {
        return PendingCheckoutRecord::query()
            ->whereNull('completed_order_id')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->subHours($this->staleRetentionHours()));
    }

    public function completedRecordsQuery(): Builder
    {
        return PendingCheckoutRecord::query()
            ->whereNotNull('completed_order_id')
            ->whereNotNull('finalized_at')
            ->where('finalized_at', '<=', now()->subHours($this->completedRetentionHours()));
    }

    protected function toData(PendingCheckoutRecord $record): PendingCheckout
    {
        return PendingCheckout::fromArray([
            'token' => $record->token,
            'reference' => $record->reference,
            'userId' => $record->user_id,
            'requestData' => $record->request_data ?? [],
            'orderPayload' => $record->order_payload ?? [],
            'paymentMethod' => $record->payment_method,
            'priceCents' => (int) $record->price_cents,
            'total' => (float) $record->total,
            'subtotal' => (float) $record->subtotal,
            'promoCodeId' => $record->promo_code_id,
            'promoCode' => $record->promo_code,
            'discountAmount' => (float) $record->discount_amount,
            'baseOrderPayload' => $record->base_order_payload ?? [],
            'metadata' => array_merge(is_array($record->metadata) ? $record->metadata : [], [
                'completedOrderId' => $record->completed_order_id,
            ]),
            'storedId' => $record->id,
            'expiresAt' => optional($record->expires_at)->toIso8601String(),
            'completedOrderId' => $record->completed_order_id,
        ]);
    }

    protected function ttlHours(): int
    {
        return max(1, (int) config('payments.pending_checkouts.ttl_hours', 24));
    }

    protected function staleRetentionHours(): int
    {
        return max($this->ttlHours(), (int) config('payments.pending_checkouts.stale_retention_hours', 24 * 7));
    }

    protected function completedRetentionHours(): int
    {
        return max(24, (int) config('payments.pending_checkouts.completed_retention_hours', 24 * 30));
    }
}
