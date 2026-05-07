<?php

namespace App\Http\Controllers;

use App\Data\Payments\PaymentCheckoutData;
use App\Http\Requests\PreviewPromoCodeRequest;
use App\Http\Requests\StoreCheckoutRequest;
use App\Services\Payments\PaymentInitializationPipeline;
use App\Services\Payments\PaymentManager;
use App\Services\Payments\PendingCheckoutStore;
use App\Services\PromoCodeService;
use App\Support\Logging\AppEventLogger;
use App\Support\Pricing\PricingEngineManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use LogicException;

class CheckoutController extends Controller
{
    public function __construct(
        protected PaymentInitializationPipeline $paymentInitializationPipeline,
        protected PendingCheckoutStore $pendingCheckoutStore,
        protected PricingEngineManager $pricingEngine,
        protected PromoCodeService $promoCodeService,
        protected PaymentManager $paymentManager,
        protected AppEventLogger $eventLogger,
    ) {}

    public function previewPromoCode(PreviewPromoCodeRequest $request): JsonResponse
    {
        $data = $request->validated();

        try {
            $basePayload = $this->resolvePricedPayload($data['orderPayload']);
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
            $basePayload = $this->resolvePricedPayload($data['orderPayload']);
        } catch (ValidationException $exception) {
            return back()->withInput()->withErrors([
                'orderPayload' => 'Please refresh the boost setup and review the highlighted pricing selections.',
                ...$exception->errors(),
            ]);
        }

        $pricing = $basePayload['pricing'] ?? [];
        $subtotal = isset($pricing['total']) ? (float) $pricing['total'] : 0;
        $promoResult = null;
        $checkoutPayload = $basePayload;

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
        }

        $total = $promoResult?->discountedTotal ?? $subtotal;
        $checkoutData = new PaymentCheckoutData(
            requestData: $data,
            orderPayload: $checkoutPayload,
            paymentMethod: $data['paymentMethod'],
            priceCents: max(0, (int) round($total * 100)),
            total: $total,
            subtotal: $subtotal,
            promoCodeId: $promoResult?->promoCode?->id,
            promoCode: $promoResult?->promoCode?->code,
            discountAmount: $promoResult?->discountAmount ?? 0,
            baseOrderPayload: $basePayload,
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
            return redirect()->route($result->target, $result->metadata['parameters'] ?? []);
        }

        return redirect()->away($result->target);
    }

    protected function resolvePricedPayload(string $encodedPayload): array
    {
        $payload = json_decode($encodedPayload, true);

        if (! $payload || ! is_array($payload)) {
            throw ValidationException::withMessages([
                'orderPayload' => 'Unable to read the saved order details.',
            ]);
        }

        return $this->pricingEngine->calculateOrFail($payload);
    }
}
