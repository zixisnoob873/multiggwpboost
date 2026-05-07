<?php

namespace App\Services\Payments\Providers;

use App\Data\Payments\PaymentCheckoutData;
use App\Data\Payments\PaymentInitializationResult;
use App\Data\Payments\PaymentVerificationResult;
use App\Data\Payments\PendingCheckout;
use App\Services\Payments\CryptomusClient;
use App\Services\Payments\PendingCheckoutStore;
use LogicException;

class CryptomusPaymentProvider extends AbstractPaymentProvider
{
    public function __construct(
        protected PendingCheckoutStore $pendingCheckoutStore,
        protected CryptomusClient $cryptomusClient,
    ) {}

    public function key(): string
    {
        return 'cryptomus';
    }

    protected function descriptorData(): array
    {
        $enabled = (bool) config('services.cryptomus.enabled', true);
        $configured = $this->isConfigured();

        return [
            'key' => 'cryptomus',
            'label' => 'Crypto payment (Cryptomus)',
            'description' => 'Pay with crypto through a hosted Cryptomus invoice.',
            'notice' => $configured
                ? 'Cryptomus redirects customers to a secure hosted crypto invoice.'
                : 'Cryptomus is enabled, but live payments need CRYPTOMUS_MERCHANT_ID and CRYPTOMUS_API_KEY before they can complete successfully.',
            'submitLabel' => 'Pay with Cryptomus',
            'isAvailable' => $enabled,
            'isDefault' => false,
            'isConfigured' => $configured,
        ];
    }

    public function initialize(PendingCheckout $pendingCheckout, PaymentCheckoutData $checkoutData): PaymentInitializationResult
    {
        if ($checkoutData->priceCents <= 0 || $checkoutData->total <= 0) {
            throw new LogicException('Zero-dollar checkout must be finalized internally.');
        }

        $invoice = $this->cryptomusClient->createInvoice([
            'amount' => number_format($checkoutData->total, 2, '.', ''),
            'currency' => 'USD',
            'order_id' => $pendingCheckout->reference,
            'url_return' => route('checkout', [], true),
            'url_success' => route('orders.success', ['checkout' => $pendingCheckout->token], true),
            'url_callback' => route('cryptomus.webhook', [], true),
            'is_payment_multiple' => false,
            'lifetime' => max(300, (int) config('services.cryptomus.invoice_lifetime', 3600)),
            'additional_data' => $pendingCheckout->token,
        ]);

        $paymentUrl = (string) ($invoice['url'] ?? '');
        $invoiceUuid = (string) ($invoice['uuid'] ?? '');

        if ($paymentUrl === '' || $invoiceUuid === '') {
            throw new LogicException('Cryptomus did not return a valid hosted invoice.');
        }

        $this->pendingCheckoutStore->put(
            $pendingCheckout->withMergedMetadata([
                'paymentProvider' => $this->key(),
                'paymentMethod' => $this->key(),
                'cryptomusInvoiceUuid' => $invoiceUuid,
                'cryptomusOrderId' => $pendingCheckout->reference,
                'cryptomusPaymentStatus' => (string) ($invoice['status'] ?? $invoice['payment_status'] ?? 'check'),
            ])
        );

        return PaymentInitializationResult::redirect($paymentUrl);
    }

    public function verify(PendingCheckout $pendingCheckout, array $payload = []): PaymentVerificationResult
    {
        $invoiceUuid = (string) ($pendingCheckout->metadata['cryptomusInvoiceUuid'] ?? '');
        $orderId = (string) ($pendingCheckout->metadata['cryptomusOrderId'] ?? $pendingCheckout->reference);

        if ($invoiceUuid === '' && $orderId === '') {
            return new PaymentVerificationResult(false);
        }

        $invoice = $invoiceUuid !== ''
            ? $this->cryptomusClient->paymentInfo(['uuid' => $invoiceUuid])
            : $this->cryptomusClient->paymentInfo(['order_id' => $orderId]);

        $status = (string) ($invoice['status'] ?? $invoice['payment_status'] ?? '');

        if (! $this->isPaidStatus($status)) {
            return new PaymentVerificationResult(false);
        }

        if ((string) ($invoice['order_id'] ?? '') !== $orderId) {
            return new PaymentVerificationResult(false);
        }

        if ($this->toCents($invoice['amount'] ?? null) !== $pendingCheckout->priceCents) {
            return new PaymentVerificationResult(false);
        }

        $uuid = (string) ($invoice['uuid'] ?? '');

        if ($uuid === '') {
            return new PaymentVerificationResult(false);
        }

        return new PaymentVerificationResult(true, [
            'payment_status' => 'paid',
            'payment_reference' => $uuid,
            'paid_at' => now(),
            'metadata' => [
                'cryptomusInvoiceUuid' => $uuid,
                'cryptomusOrderId' => $orderId,
                'cryptomusPaymentStatus' => $status,
                'cryptomusTxid' => (string) ($invoice['txid'] ?? ''),
                'cryptomusNetwork' => (string) ($invoice['network'] ?? ''),
                'cryptomusPayerCurrency' => (string) ($invoice['payer_currency'] ?? ''),
            ],
        ]);
    }

    protected function isConfigured(): bool
    {
        return filled(config('services.cryptomus.merchant_id'))
            && filled(config('services.cryptomus.api_key'));
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
