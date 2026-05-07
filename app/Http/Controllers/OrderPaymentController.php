<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Data\Payments\PendingCheckout;
use App\Services\Payments\FinalizePendingCheckoutService;
use App\Services\Payments\PaymentManager;
use App\Services\Payments\PendingCheckoutStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;

class OrderPaymentController extends Controller
{
    public function __construct(
        protected PaymentManager $paymentManager,
        protected PendingCheckoutStore $pendingCheckoutStore,
        protected FinalizePendingCheckoutService $finalizePendingCheckoutService,
    ) {}

    public function success(Request $request): JsonResponse|RedirectResponse
    {
        $checkoutToken = (string) $request->query('checkout', '');
        $sessionId = (string) $request->query('session_id', '');

        if ($checkoutToken === '') {
            $order = $sessionId !== ''
                ? Order::query()->where('stripe_session_id', $sessionId)->first()
                : null;

            if ($order && $this->canAccessExistingOrder($request, $order)) {
                return $this->redirectToOrder($request, $order);
            }

            Log::channel('payments')->warning('Payment success page was hit without a checkout token.', [
                'session_id_hash' => $this->sensitiveHash($sessionId),
                'matched_order_id' => $order?->id,
                'user_id' => $request->user()?->id,
            ]);

            return $this->paymentErrorResponse(
                $request,
                'Checkout token is required.',
                ['checkout' => ['Checkout token is required.']],
                422
            );
        }

        $pendingCheckout = $this->pendingCheckoutStore->findActive($checkoutToken);
        if (! $pendingCheckout) {
            $order = $sessionId !== ''
                ? Order::query()->where('stripe_session_id', $sessionId)->first()
                : null;

            if ($order && $this->canAccessExistingOrder($request, $order)) {
                return $this->redirectToOrder($request, $order);
            }

            Log::channel('payments')->warning('Payment success page received an expired or missing checkout session.', [
                'checkout_token_hash' => $this->sensitiveHash($checkoutToken),
                'session_id_hash' => $this->sensitiveHash($sessionId),
                'matched_order_id' => $order?->id,
                'user_id' => $request->user()?->id,
            ]);

            return $this->paymentErrorResponse(
                $request,
                'Your payment session expired. Please start checkout again.',
                ['payment' => ['Your payment session expired. Please start checkout again.']],
                422
            );
        }

        if ($ownerError = $this->assertPendingCheckoutOwner($request, $pendingCheckout)) {
            return $ownerError;
        }

        $completedOrderId = $pendingCheckout->metadata['completedOrderId'] ?? null;
        if ($completedOrderId) {
            $existingOrder = Order::find($completedOrderId);

            if ($existingOrder) {
                return $this->redirectToOrder(
                    $request,
                    $existingOrder,
                    is_string($pendingCheckout->metadata['successMessage'] ?? null) ? $pendingCheckout->metadata['successMessage'] : null
                );
            }
        }

        $providerKey = (string) ($pendingCheckout->metadata['paymentProvider'] ?? $pendingCheckout->paymentMethod);
        $provider = $this->paymentManager->provider($providerKey);

        try {
            $verification = $provider->verify($pendingCheckout, $request->query());
        } catch (\Throwable $exception) {
            Log::channel('payments')->error('Payment verification threw an exception on the success page.', [
                'checkout_reference' => $pendingCheckout->reference,
                'session_id_hash' => $this->sensitiveHash($sessionId),
                'provider' => $providerKey,
                'user_id' => $request->user()?->id,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return $this->paymentErrorResponse(
                $request,
                'We could not verify your payment yet. If you were charged, please contact support before retrying.',
                ['payment' => ['We could not verify your payment yet. If you were charged, please contact support before retrying.']],
                500
            );
        }

        if (! $verification->isPaid) {
            Log::channel('payments')->warning('Payment verification returned unpaid on the success page.', [
                'checkout_reference' => $pendingCheckout->reference,
                'session_id_hash' => $this->sensitiveHash($sessionId),
                'provider' => $providerKey,
                'user_id' => $request->user()?->id,
            ]);

            return $this->paymentErrorResponse(
                $request,
                'We could not verify your payment yet.',
                ['payment' => ['We could not verify your payment yet.']],
                422
            );
        }

        try {
            $order = $this->finalizePendingCheckoutService->finalize(
                $pendingCheckout,
                $providerKey,
                $verification->updates,
            );
        } catch (ValidationException $exception) {
            Log::channel('payments')->warning('Payment finalization failed validation on the success page.', [
                'checkout_reference' => $pendingCheckout->reference,
                'session_id_hash' => $this->sensitiveHash($sessionId),
                'provider' => $providerKey,
                'user_id' => $request->user()?->id,
                'errors' => $exception->errors(),
            ]);

            return $this->paymentErrorResponse(
                $request,
                'We could not finalize your order because some checkout details need attention.',
                $exception->errors(),
                422
            );
        } catch (\Throwable $exception) {
            Log::channel('payments')->error('Payment finalization failed on the success page.', [
                'checkout_reference' => $pendingCheckout->reference,
                'session_id_hash' => $this->sensitiveHash($sessionId),
                'provider' => $providerKey,
                'user_id' => $request->user()?->id,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return $this->paymentErrorResponse(
                $request,
                'We could not finalize your order yet. If you were charged, please contact support before retrying.',
                ['payment' => ['We could not finalize your order yet. If you were charged, please contact support before retrying.']],
                500
            );
        }

        return $this->redirectToOrder(
            $request,
            $order,
            is_string($pendingCheckout->metadata['successMessage'] ?? null) ? $pendingCheckout->metadata['successMessage'] : null
        );
    }

    protected function redirectToOrder(Request $request, Order $order, ?string $status = null): JsonResponse|RedirectResponse
    {
        if ($this->shouldReturnJson($request)) {
            return $this->jsonResponse(
                'success',
                $status ?: 'Payment verified successfully.',
                [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'redirect_url' => route('user-chats.show', ['order' => $order]),
                ],
                null,
                200
            );
        }

        $redirect = Redirect::to(route('user-chats.show', ['order' => $order]));

        if ($status) {
            $redirect->with('status', $status);
        }

        return $redirect;
    }

    protected function paymentErrorResponse(Request $request, string $message, array $errors, int $status): JsonResponse|RedirectResponse
    {
        if ($this->shouldReturnJson($request)) {
            return $this->jsonResponse('error', $message, null, $errors, $status);
        }

        return Redirect::to(route('checkout'))
            ->withErrors($errors);
    }

    protected function canAccessExistingOrder(Request $request, Order $order): bool
    {
        $user = $request->user();

        return $user !== null && $user->can('view', $order);
    }

    protected function assertPendingCheckoutOwner(Request $request, PendingCheckout $pendingCheckout): ?JsonResponse
    {
        $user = $request->user();

        if ($user && (int) $pendingCheckout->userId === (int) $user->getKey()) {
            return null;
        }

        Log::channel('payments')->warning('Payment success owner check failed.', [
            'checkout_token_hash' => $this->sensitiveHash($pendingCheckout->token),
            'pending_user_id' => $pendingCheckout->userId,
            'request_user_id' => $user?->id,
        ]);

        if ($this->shouldReturnJson($request)) {
            return $this->jsonResponse(
                'error',
                'You are not allowed to access this checkout.',
                null,
                ['checkout' => ['You are not allowed to access this checkout.']],
                403
            );
        }

        abort(403, 'You are not allowed to access this checkout.');
    }

    protected function sensitiveHash(string $value): ?string
    {
        $value = trim($value);

        return $value !== '' ? hash('sha256', $value) : null;
    }

    protected function shouldReturnJson(Request $request): bool
    {
        return $request->expectsJson() || $request->wantsJson();
    }

    protected function jsonResponse(string $status, string $message, mixed $data, ?array $errors, int $code): JsonResponse
    {
        return response()->json([
            'status' => $status,
            'message' => $message,
            'data' => $data,
            'errors' => $errors,
        ], $code);
    }
}
