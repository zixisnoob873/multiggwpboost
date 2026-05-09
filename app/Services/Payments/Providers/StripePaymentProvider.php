<?php

namespace App\Services\Payments\Providers;

use App\Data\Payments\PaymentCheckoutData;
use App\Data\Payments\PaymentInitializationResult;
use App\Data\Payments\PaymentVerificationResult;
use App\Data\Payments\PendingCheckout;
use App\Services\Payments\PendingCheckoutStore;
use LogicException;
use Stripe\StripeClient;

class StripePaymentProvider extends AbstractPaymentProvider
{
    public function __construct(protected PendingCheckoutStore $pendingCheckoutStore) {}

    public function key(): string
    {
        return 'stripe';
    }

    protected function descriptorData(): array
    {
        $enabled = (bool) config('services.stripe.enabled', true);
        $configured = filled(config('services.stripe.secret'));

        return [
            'key' => 'stripe',
            'label' => 'Instant card payment (Stripe)',
            'description' => 'Secure card payments processed by Stripe.',
            'notice' => $configured
                ? 'Stripe processes card payments instantly.'
                : 'Stripe is enabled, but live payment credentials must be configured before customers can complete payment.',
            'submitLabel' => 'Pay with Stripe',
            'isAvailable' => $enabled,
            'isDefault' => $enabled,
            'isConfigured' => $configured,
        ];
    }

    public function initialize(PendingCheckout $pendingCheckout, PaymentCheckoutData $checkoutData): PaymentInitializationResult
    {
        if ($checkoutData->priceCents <= 0 || $checkoutData->total <= 0) {
            throw new LogicException('Zero-dollar checkout must be finalized internally.');
        }

        $secret = $this->configuredSecret();
        $client = new StripeClient($secret);
        $session = $client->checkout->sessions->create([
            'mode' => 'payment',
            'customer_email' => $checkoutData->requestData['email'],
            'line_items' => [[
                'price_data' => [
                    'currency' => 'usd',
                    'product_data' => [
                        'name' => $checkoutData->orderPayload['orderType'] ?? 'Rank Boosting',
                    ],
                    'unit_amount' => $checkoutData->priceCents,
                ],
                'quantity' => 1,
            ]],
            'metadata' => [
                'checkout_token' => $pendingCheckout->token,
                'checkout_reference' => $pendingCheckout->reference,
                'user_id' => $pendingCheckout->userId,
            ],
            'success_url' => route('orders.success', [], true).'?session_id={CHECKOUT_SESSION_ID}&checkout='.$pendingCheckout->token,
            'cancel_url' => route('checkout', [], true),
        ]);

        $this->pendingCheckoutStore->put(
            $pendingCheckout->withMergedMetadata([
                'paymentProvider' => $this->key(),
                'paymentMethod' => $this->key(),
                'stripeSessionId' => $session->id,
            ])
        );

        return PaymentInitializationResult::redirect($session->url);
    }

    public function verify(PendingCheckout $pendingCheckout, array $payload = []): PaymentVerificationResult
    {
        $sessionId = $payload['session_id'] ?? null;
        if (! $sessionId) {
            return new PaymentVerificationResult(false);
        }

        $secret = $this->configuredSecret();
        $client = new StripeClient($secret);
        $session = $client->checkout->sessions->retrieve($sessionId, []);

        if (($session->metadata['checkout_token'] ?? null) !== $pendingCheckout->token) {
            return new PaymentVerificationResult(false);
        }

        if ($session->payment_status !== 'paid') {
            return new PaymentVerificationResult(false);
        }

        if ((int) ($session->amount_total ?? 0) !== $pendingCheckout->priceCents) {
            return new PaymentVerificationResult(false);
        }

        return new PaymentVerificationResult(true, [
            'payment_status' => 'paid',
            'payment_reference' => is_string($session->payment_intent ?? null) ? $session->payment_intent : null,
            'stripe_session_id' => $session->id,
        ]);
    }

    protected function configuredSecret(): string
    {
        $secret = (string) config('services.stripe.secret', '');

        if ($secret === '') {
            throw new LogicException('Stripe is not configured.');
        }

        return $secret;
    }
}
