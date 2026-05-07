<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\Payments\FinalizePendingCheckoutService;
use App\Services\Payments\PaymentWebhookEventService;
use App\Services\Payments\PendingCheckoutStore;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Exception\UnexpectedValueException;
use Stripe\Webhook;

class StripeWebhookController extends Controller
{
    public function __construct(
        protected PendingCheckoutStore $pendingCheckoutStore,
        protected FinalizePendingCheckoutService $finalizePendingCheckoutService,
        protected PaymentWebhookEventService $paymentWebhookEventService,
    ) {}

    public function __invoke(Request $request): Response
    {
        $payload = $request->getContent();
        $signature = (string) $request->header('Stripe-Signature', '');
        $secret = (string) config('services.stripe.webhook_secret', '');

        if ($secret === '') {
            Log::channel('webhooks')->error('Stripe webhook secret is not configured.', [
                'ip' => $request->ip(),
            ]);

            return response('Stripe webhook secret is not configured.', 500);
        }

        try {
            $event = Webhook::constructEvent($payload, $signature, $secret);
        } catch (UnexpectedValueException|SignatureVerificationException $exception) {
            Log::channel('webhooks')->warning('Stripe webhook signature validation failed.', [
                'ip' => $request->ip(),
                'signature_present' => $signature !== '',
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return response('Invalid webhook payload.', 400);
        }

        $decodedPayload = json_decode($payload, true);
        $decodedPayload = is_array($decodedPayload) ? $decodedPayload : [];
        $idempotencyKey = trim((string) $event->id);

        if ($idempotencyKey === '') {
            Log::channel('webhooks')->warning('Stripe webhook payload is missing the event identifier required for idempotency.', [
                'ip' => $request->ip(),
                'type' => $event->type,
            ]);

            return response('Missing webhook event identifier.', 400);
        }

        if (! in_array($event->type, [
            'checkout.session.completed',
            'checkout.session.async_payment_succeeded',
            'checkout.session.async_payment_failed',
            'payment_intent.payment_failed',
        ], true)) {
            return response('Ignored.', 200);
        }

        /** @var \Stripe\Checkout\Session $session */
        $session = $event->data->object;
        $checkoutToken = (string) ($session->metadata->checkout_token ?? '');
        $stripeSessionId = is_string($session->id ?? null) ? $session->id : null;
        $paymentReference = is_string($session->payment_intent ?? null) ? $session->payment_intent : null;
        $pendingRecord = $checkoutToken !== '' ? $this->pendingCheckoutStore->recordForToken($checkoutToken) : null;
        $eventState = $this->paymentWebhookEventService->begin(
            'stripe',
            $idempotencyKey,
            (string) $event->type,
            $decodedPayload,
            $pendingRecord?->id,
        );

        if ($eventState->alreadyHandled()) {
            return response('Received.', 200);
        }

        if ($eventState->alreadyProcessing()) {
            Log::channel('webhooks')->warning('Stripe webhook replay arrived while the same event is already processing.', [
                'event_id' => $event->id,
                'type' => $event->type,
            ]);

            return response('Webhook event is already processing.', 409);
        }

        $processedEvent = $eventState->event;

        if ($checkoutToken === '') {
            Log::channel('webhooks')->warning('Stripe webhook payload is missing the checkout token.', [
                'event_id' => $event->id,
                'type' => $event->type,
                'stripe_session_id_hash' => $this->sensitiveHash($stripeSessionId),
            ]);
            $this->paymentWebhookEventService->markFailed($processedEvent, 'Missing checkout token.');

            return response('Missing checkout token.', 400);
        }

        if (! in_array($event->type, [
            'checkout.session.completed',
            'checkout.session.async_payment_succeeded',
        ], true)) {
            Log::channel('webhooks')->info('Stripe webhook received non-success event.', [
                'type' => $event->type,
                'id' => $event->id,
            ]);
            $this->paymentWebhookEventService->markIgnored($processedEvent, $pendingRecord?->id, 'Stripe event does not finalize checkout.');

            return response('Received.', 200);
        }

        $pendingCheckout = $this->pendingCheckoutStore->find($checkoutToken);
        if (! $pendingCheckout) {
            $existingOrder = Order::query()
                ->where(function ($query) use ($stripeSessionId, $paymentReference) {
                    if ($stripeSessionId) {
                        $query->orWhere('stripe_session_id', $stripeSessionId);
                    }

                    if ($paymentReference) {
                        $query->orWhere('payment_reference', $paymentReference);
                    }
                })
                ->first();

            if ($existingOrder) {
                Log::channel('webhooks')->info('Stripe webhook matched an already-finalized order after the pending checkout expired.', [
                    'checkout_token_hash' => $this->sensitiveHash($checkoutToken),
                    'event_id' => $event->id,
                    'order_id' => $existingOrder->id,
                    'stripe_session_id_hash' => $this->sensitiveHash($stripeSessionId),
                    'payment_reference' => $paymentReference,
                ]);
                $this->paymentWebhookEventService->markProcessed($processedEvent, $existingOrder->id);

                return response('Received.', 200);
            }

            Log::channel('webhooks')->warning('Stripe webhook could not find the pending checkout or an existing finalized order.', [
                'checkout_token_hash' => $this->sensitiveHash($checkoutToken),
                'event_id' => $event->id,
                'stripe_session_id_hash' => $this->sensitiveHash($stripeSessionId),
                'payment_reference' => $paymentReference,
            ]);
            $this->paymentWebhookEventService->markFailed($processedEvent, 'Pending checkout not found.');

            return response('Checkout not found.', 404);
        }

        if (($session->payment_status ?? null) !== 'paid') {
            Log::channel('webhooks')->info('Stripe webhook received a checkout session that is not paid.', [
                'checkout_reference' => $pendingCheckout->reference,
                'checkout_token_hash' => $this->sensitiveHash($checkoutToken),
                'event_id' => $event->id,
                'stripe_session_id_hash' => $this->sensitiveHash($stripeSessionId),
                'payment_status' => $session->payment_status ?? null,
            ]);
            $this->paymentWebhookEventService->markIgnored($processedEvent, $pendingCheckout->storedId, 'Stripe session is not paid.');

            return response('Payment not completed.', 200);
        }

        if ((int) ($session->amount_total ?? 0) !== $pendingCheckout->priceCents) {
            Log::channel('webhooks')->warning('Stripe webhook amount mismatch detected.', [
                'checkout_reference' => $pendingCheckout->reference,
                'checkout_token_hash' => $this->sensitiveHash($checkoutToken),
                'event_id' => $event->id,
                'stripe_session_id_hash' => $this->sensitiveHash($stripeSessionId),
                'expected_amount_cents' => $pendingCheckout->priceCents,
                'received_amount_cents' => (int) ($session->amount_total ?? 0),
            ]);
            $this->paymentWebhookEventService->markFailed($processedEvent, 'Amount mismatch.', $pendingCheckout->storedId);

            return response('Amount mismatch.', 400);
        }

        try {
            $order = DB::transaction(function () use ($pendingCheckout, $paymentReference, $stripeSessionId, $event, $processedEvent) {
                $order = $this->finalizePendingCheckoutService->finalize($pendingCheckout, 'stripe', [
                    'payment_status' => 'paid',
                    'payment_reference' => $paymentReference,
                    'stripe_session_id' => $stripeSessionId,
                    'paid_at' => now(),
                    'metadata' => array_merge($pendingCheckout->metadata, [
                        'stripeEventId' => $event->id,
                    ]),
                ]);

                $this->paymentWebhookEventService->markProcessed($processedEvent, $order->id, $pendingCheckout->storedId);

                return $order;
            }, 3);
        } catch (\Throwable $exception) {
            Log::channel('webhooks')->error('Stripe webhook failed to finalize checkout.', [
                'checkout_reference' => $pendingCheckout->reference,
                'checkout_token_hash' => $this->sensitiveHash($checkoutToken),
                'event_id' => $event->id,
                'stripe_session_id_hash' => $this->sensitiveHash($stripeSessionId),
                'payment_reference' => $paymentReference,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
            $this->paymentWebhookEventService->markFailed($processedEvent, $exception, $pendingCheckout->storedId);

            return response('Webhook processing failed.', 500);
        }

        return response('Received.', 200);
    }

    protected function sensitiveHash(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? hash('sha256', $value) : null;
    }
}
