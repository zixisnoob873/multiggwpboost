<?php

namespace App\Http\Controllers;

use App\Data\Payments\PaymentCheckoutData;
use App\Data\Pricing\PricingRequest;
use App\Data\Pricing\PricingResult;
use App\Http\Requests\PreviewPromoCodeRequest;
use App\Http\Requests\StoreCheckoutRequest;
use App\Services\Payments\PaymentInitializationPipeline;
use App\Services\Payments\PaymentManager;
use App\Services\Payments\PendingCheckoutStore;
use App\Services\Checkout\CheckoutSelectionResolver;
use App\Services\Pricing\PricingCalculator;
use App\Services\PromoCodeService;
use App\Support\Logging\AppEventLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use LogicException;

class CheckoutController extends Controller
{
    public function __construct(
        protected PaymentInitializationPipeline $paymentInitializationPipeline,
        protected PendingCheckoutStore $pendingCheckoutStore,
        protected PricingCalculator $pricingCalculator,
        protected PromoCodeService $promoCodeService,
        protected PaymentManager $paymentManager,
        protected AppEventLogger $eventLogger,
        protected CheckoutSelectionResolver $selectionResolver,
    ) {}

    public function previewPromoCode(PreviewPromoCodeRequest $request): JsonResponse
    {
        $data = $request->validated();

        try {
            $baseResult = $this->resolvePricedPayload($data['orderPayload']);
            $basePayload = $baseResult->toArray();
        } catch (ValidationException $exception) {
            return response()->json([
                'success' => false,
                'message' => 'Please review your boost setup before applying a promo code.',
                'error_code' => 'validation_failed',
                'errors' => $exception->errors(),
            ], 422);
        }

        $promoResult = $this->promoCodeService->resolveCodeForPayload(
            $data['promoCode'],
            $basePayload,
            $request->user()?->id,
        );

        if (! $promoResult->valid) {
            $validationErrors = $promoResult->validationErrors !== []
                ? $promoResult->validationErrors
                : ['promoCode' => $promoResult->errors];

            $this->eventLogger->payment('promo.preview_failed', [
                'user_id' => $request->user()?->id,
                'promo_code' => $data['promoCode'],
                'errors' => $validationErrors,
            ], 'warning');

            return response()->json([
                'success' => false,
                'message' => $promoResult->firstError(),
                'error_code' => 'validation_failed',
                'errors' => $validationErrors,
            ], 422);
        }

        $this->eventLogger->payment('promo.preview_succeeded', [
            'user_id' => $request->user()?->id,
            'promo_code_id' => $promoResult->promoCode?->id,
            'promo_code' => $promoResult->promoCode?->code,
            'original_total' => $promoResult->orderAmount,
            'discounted_total' => $promoResult->discountedTotal,
            'discount_amount' => $promoResult->discountAmount,
            'promo_managed_addons' => $promoResult->promoManagedAddons,
            'promo_added_addons' => $promoResult->promoAddedAddons,
        ]);

        return response()->json([
            'message' => "{$promoResult->promoCode?->code} applied successfully.",
            'promo' => $promoResult->toArray(),
            'order' => $promoResult->resolvedOrderPayload,
            'pricing' => [
                'subtotal' => round($promoResult->orderAmount, 2),
                'originalTotal' => round($promoResult->orderAmount, 2),
                'discountAmount' => round($promoResult->discountAmount, 2),
                'finalTotal' => round($promoResult->discountedTotal, 2),
            ],
        ]);
    }

    public function store(StoreCheckoutRequest $request)
    {
        $data = $request->validated();

        try {
            $baseResult = $this->resolvePricedPayload($data['orderPayload']);
            $this->assertSubmittedPriceMatches($baseResult);
            $basePayload = $baseResult->toArray();
        } catch (ValidationException $exception) {
            return back()->withInput()->withErrors([
                'orderPayload' => 'Please refresh the boost setup and review the highlighted pricing selections.',
                ...$exception->errors(),
            ]);
        }

        $pricingEvidence = $baseResult->pricingEvidence();
        $subtotal = $baseResult->finalPrice;
        $promoResult = null;
        $checkoutPayload = $basePayload;
        $priceCents = $baseResult->finalPriceCents;

        if (filled($data['promoCode'] ?? null)) {
            $promoResult = $this->promoCodeService->resolveCodeForPayload(
                $data['promoCode'],
                $basePayload,
                $request->user()?->id,
            );

            if (! $promoResult->valid) {
                return back()->withInput()->withErrors(
                    $promoResult->validationErrors !== []
                        ? $promoResult->validationErrors
                        : ['promoCode' => $promoResult->errors]
                );
            }

            $subtotal = $promoResult->orderAmount;
            $checkoutPayload = $promoResult->originalOrderPayload;
            $priceCents = max(0, (int) round($promoResult->discountedTotal * 100));
        }

        $total = $promoResult?->discountedTotal ?? $subtotal;
        $checkoutContact = [
            'contactMethod' => $data['contactMethod'],
            'email' => $data['email'],
            'whatsapp' => $data['whatsapp'] ?? null,
            'discord' => $data['discord'] ?? null,
            'customerNotes' => $data['customerNotes'] ?? null,
        ];
        $checkoutData = new PaymentCheckoutData(
            requestData: $data,
            orderPayload: $checkoutPayload,
            paymentMethod: $data['paymentMethod'],
            priceCents: $priceCents,
            total: $total,
            subtotal: $subtotal,
            promoCodeId: $promoResult?->promoCode?->id,
            promoCode: $promoResult?->promoCode?->code,
            discountAmount: $promoResult?->discountAmount ?? 0,
            baseOrderPayload: $basePayload,
            metadata: [
                'calculator' => $this->selectionResolver->calculatorMetadata($checkoutPayload, $pricingEvidence),
                'checkout' => array_filter($checkoutContact, static fn (mixed $value): bool => $value !== null && $value !== ''),
                'pricingCalculation' => $pricingEvidence,
                'pricing' => [
                    'calculation' => $pricingEvidence,
                    'calculated_cents' => $baseResult->finalPriceCents,
                    'checkout_cents' => $priceCents,
                ],
            ],
        );

        if ($checkoutData->priceCents > 0 || $checkoutData->total > 0) {
            $selectedProvider = collect($this->paymentManager->allDescriptors())
                ->firstWhere('key', $checkoutData->paymentMethod);

            if (! $selectedProvider || ! ($selectedProvider['isAvailable'] ?? false)) {
                return back()->withInput()->withErrors([
                    'payment' => 'This payment method is unavailable right now. Please choose another option.',
                ]);
            }

            if (! ($selectedProvider['isConfigured'] ?? false)) {
                return back()->withInput()->withErrors([
                    'payment' => 'This payment method is unavailable right now. Please choose another option or contact support.',
                ]);
            }
        }

        $pendingCheckout = null;

        try {
            $pendingCheckout = $this->pendingCheckoutStore->create($request->user()->id, $checkoutData);
            $this->eventLogger->payment('payment.initialization_started', [
                'user_id' => $request->user()?->id,
                'provider' => $checkoutData->paymentMethod,
                'price_cents' => $checkoutData->priceCents,
                'checkout_reference' => $pendingCheckout->reference,
                'checkout_kind' => 'default',
            ]);
            $result = $this->paymentInitializationPipeline->initialize($pendingCheckout, $checkoutData);
            $this->eventLogger->payment('payment.initialization_ready', [
                'user_id' => $request->user()?->id,
                'provider' => $checkoutData->paymentMethod,
                'price_cents' => $checkoutData->priceCents,
                'checkout_reference' => $pendingCheckout->reference,
                'checkout_kind' => 'default',
                'redirect_type' => $result->type,
            ]);
        } catch (ValidationException $exception) {
            Log::channel('payments')->warning('Payment initialization validation failed.', [
                'user_id' => $request->user()?->id,
                'payment_method' => $checkoutData->paymentMethod,
                'price_cents' => $checkoutData->priceCents,
                'checkout_reference' => $pendingCheckout?->reference,
                'errors' => $exception->errors(),
            ]);

            return back()->withInput()->withErrors($exception->errors());
        } catch (LogicException $exception) {
            Log::channel('payments')->error('Payment initialization hit a non-recoverable configuration error.', [
                'user_id' => $request->user()?->id,
                'payment_method' => $checkoutData->paymentMethod,
                'price_cents' => $checkoutData->priceCents,
                'checkout_reference' => $pendingCheckout?->reference,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return back()->withInput()->withErrors([
                'payment' => $exception->getMessage(),
            ]);
        } catch (\Throwable $exception) {
            Log::channel('payments')->error('Payment initialization failed.', [
                'user_id' => $request->user()?->id,
                'payment_method' => $checkoutData->paymentMethod,
                'price_cents' => $checkoutData->priceCents,
                'checkout_reference' => $pendingCheckout?->reference,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return back()->withInput()->withErrors([
                'payment' => 'We could not start your payment. Please try again in a moment.',
            ]);
        }

        if ($result->type === 'route') {
            return redirect()
                ->route($result->target, $result->metadata['parameters'] ?? [])
                ->with('analyticsEvents', [
                    $this->checkoutCompletedAnalyticsEvent($checkoutData),
                ]);
        }

        return redirect()->away($result->target);
    }

    protected function checkoutCompletedAnalyticsEvent(PaymentCheckoutData $checkoutData): array
    {
        $payload = $checkoutData->orderPayload;
        $addons = is_array($payload['selectedAddons'] ?? null)
            ? $payload['selectedAddons']
            : (is_array($payload['addons'] ?? null) ? $payload['addons'] : []);
        $addonCount = count(array_filter($addons));

        return [
            'name' => 'checkout_completed',
            'payload' => [
                'context' => 'checkout',
                'provider' => $checkoutData->paymentMethod,
                'payment_method' => $checkoutData->paymentMethod,
                'game_slug' => (string) ($payload['gameSlug'] ?? ''),
                'game_name' => (string) ($payload['game'] ?? ''),
                'service_slug' => (string) ($payload['serviceSlug'] ?? ''),
                'service_type' => (string) ($payload['serviceType'] ?? $payload['orderType'] ?? ''),
                'has_addons' => $addonCount > 0,
                'addon_count' => $addonCount,
                'has_promo' => filled($checkoutData->promoCode),
                'checkout_kind' => 'default',
            ],
        ];
    }

    protected function resolvePricedPayload(string $encodedPayload): PricingResult
    {
        if (strlen($encodedPayload) > 20000) {
            throw ValidationException::withMessages([
                'orderPayload' => 'The saved order details are too large. Please refresh the boost setup.',
            ]);
        }

        $payload = json_decode($encodedPayload, true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($payload) || array_is_list($payload)) {
            throw ValidationException::withMessages([
                'orderPayload' => 'Unable to read the saved order details.',
            ]);
        }

        return $this->pricingCalculator->calculateOrFail(PricingRequest::fromArray($payload));
    }

    protected function assertSubmittedPriceMatches(PricingResult $result): void
    {
        $mismatches = [];

        foreach ($result->request->clientSubmittedTotals as $field => $amount) {
            $submittedCents = max(0, (int) round(((float) $amount) * 100));

            if ($submittedCents === $result->finalPriceCents) {
                continue;
            }

            $mismatches[$field] = [
                'submitted' => round((float) $amount, 2),
                'submitted_cents' => $submittedCents,
            ];
        }

        if ($mismatches === []) {
            return;
        }

        $message = 'Your saved quote no longer matches the current price. Please refresh your order summary and try again.';

        $this->eventLogger->payment('checkout.price_tampering_rejected', [
            'game_slug' => $result->payload['gameSlug'] ?? $result->request->gameSlug,
            'service_slug' => $result->payload['serviceSlug'] ?? $result->request->serviceSlug,
            'service_type' => $result->payload['serviceType'] ?? $result->request->serviceType ?? $result->request->orderType,
            'calculated_total' => $result->finalPrice,
            'calculated_cents' => $result->finalPriceCents,
            'submitted_totals' => $mismatches,
        ], 'warning');

        throw ValidationException::withMessages([
            'orderPayload' => $message,
            'pricing' => $message,
            ...collect(array_keys($mismatches))
                ->mapWithKeys(fn (string $field): array => [$field => 'The submitted checkout total does not match the current quote.'])
                ->all(),
        ]);
    }
}
