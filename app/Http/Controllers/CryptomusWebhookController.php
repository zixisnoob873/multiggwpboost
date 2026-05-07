<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\Payments\CryptomusClient;
use App\Services\Payments\FinalizePendingCheckoutService;
use App\Services\Payments\PaymentWebhookEventService;
use App\Services\Payments\PendingCheckoutStore;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CryptomusWebhookController extends Controller
{
    public function __construct(
        protected PendingCheckoutStore $pendingCheckoutStore,
        protected FinalizePendingCheckoutService $finalizePendingCheckoutService,
        protected PaymentWebhookEventService $paymentWebhookEventService,
        protected CryptomusClient $cryptomusClient,
    ) {}

    public function __invoke(Request $request): Response
    {
        if (! filled(config('services.cryptomus.api_key')) || ! filled(config('services.cryptomus.merchant_id'))) {
            Log::channel('webhooks')->error('Cryptomus credentials are not configured.', [
                'ip' => $request->ip(),
            ]);

            return response('Cryptomus is not configured.', 500);
        }

        $payload = $request->json()->all();
        $payload = is_array($payload) ? $payload : [];

        if ($payload === []) {
            Log::channel('webhooks')->warning('Cryptomus webhook received an empty payload.', [
                'ip' => $request->ip(),
            ]);

            return response('Invalid webhook payload.', 400);
        }

        if (! $this->cryptomusClient->verifyWebhookSignature($payload)) {
            Log::channel('webhooks')->warning('Cryptomus webhook signature validation failed.', [
                'ip' => $request->ip(),
                'order_id' => $payload['order_id'] ?? null,
                'uuid' => $payload['uuid'] ?? null,
            ]);

            return response('Invalid webhook signature.', 400);
        }

        $orderId = (string) ($payload['order_id'] ?? '');
        $invoiceUuid = (string) ($payload['uuid'] ?? '');
        $status = (string) ($payload['status'] ?? '');
        $paymentReference = $invoiceUuid !== '' ? $invoiceUuid : null;
        $pendingRecord = $orderId !== '' ? $this->pendingCheckoutStore->recordForReference($orderId) : null;
        $eventId = $this->eventId($payload, $invoiceUuid, $orderId, $status);
        $idempotencyKey = trim($eventId);

        if ($idempotencyKey === '') {
            Log::channel('webhooks')->warning('Cryptomus webhook payload is missing the event identifier required for idempotency.', [
                'ip' => $request->ip(),
                'order_id' => $orderId !== '' ? $orderId : null,
                'uuid' => $invoiceUuid !== '' ? $invoiceUuid : null,
            ]);

            return response('Missing webhook event identifier.', 400);
        }

        $eventState = $this->paymentWebhookEventService->begin(
            'cryptomus',
            $idempotencyKey,
            $status !== '' ? $status : null,
            $payload,
            $pendingRecord?->id,
        );

        if ($eventState->alreadyHandled()) {
            return response('Received.', 200);
        }

        if ($eventState->alreadyProcessing()) {
            Log::channel('webhooks')->warning('Cryptomus webhook replay arrived while the same event is already processing.', [
                'event_id' => $eventId,
                'status' => $status,
            ]);

            return response('Webhook event is already processing.', 409);
        }

        $processedEvent = $eventState->event;

        if ($orderId === '') {
            Log::channel('webhooks')->warning('Cryptomus webhook payload is missing the order ID.', [
                'event_id' => $eventId,
                'uuid' => $invoiceUuid ?: null,
            ]);
            $this->paymentWebhookEventService->markFailed($processedEvent, 'Missing order ID.');

            return response('Missing order ID.', 400);
        }

        if (! $this->isPaidStatus($status)) {
            Log::channel('webhooks')->info('Cryptomus webhook received a non-success payment status.', [
                'event_id' => $eventId,
                'order_id' => $orderId,
                'status' => $status,
            ]);
            $this->paymentWebhookEventService->markIgnored($processedEvent, $pendingRecord?->id, 'Cryptomus payment is not finalized as paid.');

            return response('Received.', 200);
        }

        $pendingCheckout = $this->pendingCheckoutStore->findByReference($orderId);

        if (! $pendingCheckout) {
            $existingOrder = $paymentReference !== null
                ? Order::query()->where('payment_reference', $paymentReference)->first()
                : null;

            if ($existingOrder) {
                Log::channel('webhooks')->info('Cryptomus webhook matched an already-finalized order after the pending checkout expired.', [
                    'event_id' => $eventId,
                    'order_id' => $orderId,
                    'uuid' => $invoiceUuid ?: null,
                    'order_record_id' => $existingOrder->id,
                ]);
                $this->paymentWebhookEventService->markProcessed($processedEvent, $existingOrder->id);

                return response('Received.', 200);
            }

            Log::channel('webhooks')->warning('Cryptomus webhook could not find the pending checkout or an existing finalized order.', [
                'event_id' => $eventId,
                'order_id' => $orderId,
                'uuid' => $invoiceUuid ?: null,
            ]);
            $this->paymentWebhookEventService->markFailed($processedEvent, 'Pending checkout not found.');

            return response('Checkout not found.', 404);
        }

        if ($this->toCents($payload['amount'] ?? null) !== $pendingCheckout->priceCents) {
            Log::channel('webhooks')->warning('Cryptomus webhook amount mismatch detected.', [
                'event_id' => $eventId,
                'checkout_reference' => $pendingCheckout->reference,
                'expected_amount_cents' => $pendingCheckout->priceCents,
                'received_amount_cents' => $this->toCents($payload['amount'] ?? null),
            ]);
            $this->paymentWebhookEventService->markFailed($processedEvent, 'Amount mismatch.', $pendingCheckout->storedId);

            return response('Amount mismatch.', 400);
        }

        try {
            $order = DB::transaction(function () use ($pendingCheckout, $paymentReference, $invoiceUuid, $orderId, $status, $payload, $processedEvent) {
                $order = $this->finalizePendingCheckoutService->finalize($pendingCheckout, 'cryptomus', [
                    'payment_status' => 'paid',
                    'payment_reference' => $paymentReference,
                    'paid_at' => now(),
                    'metadata' => array_merge($pendingCheckout->metadata, [
                        'cryptomusInvoiceUuid' => $invoiceUuid,
                        'cryptomusOrderId' => $orderId,
                        'cryptomusPaymentStatus' => $status,
                        'cryptomusTxid' => (string) ($payload['txid'] ?? ''),
                        'cryptomusNetwork' => (string) ($payload['network'] ?? ''),
                        'cryptomusPayerCurrency' => (string) ($payload['payer_currency'] ?? ''),
                    ]),
                ]);

                $this->paymentWebhookEventService->markProcessed($processedEvent, $order->id, $pendingCheckout->storedId);

                return $order;
            }, 3);
        } catch (\Throwable $exception) {
            Log::channel('webhooks')->error('Cryptomus webhook failed to finalize checkout.', [
                'event_id' => $eventId,
                'checkout_reference' => $pendingCheckout->reference,
                'order_id' => $orderId,
                'uuid' => $invoiceUuid ?: null,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
            $this->paymentWebhookEventService->markFailed($processedEvent, $exception, $pendingCheckout->storedId);

            return response('Webhook processing failed.', 500);
        }

        return response('Received.', 200);
    }

    protected function eventId(array $payload, string $invoiceUuid, string $orderId, string $status): string
    {
        $base = $invoiceUuid !== '' ? $invoiceUuid : ($orderId !== '' ? $orderId : 'cryptomus');
        $txid = (string) ($payload['txid'] ?? 'no-txid');

        return implode(':', [$base, $status !== '' ? $status : 'unknown', $txid !== '' ? $txid : 'no-txid']);
    }

    protected function isPaidStatus(string $status): bool
    {
        return in_array($status, ['paid', 'paid_over'], true);
    }

    protected function toCents(mixed $amount): int
    {
        return (int) round((float) $amount * 100);
    }
}
