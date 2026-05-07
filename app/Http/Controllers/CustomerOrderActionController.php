<?php

namespace App\Http\Controllers;

use App\Http\Requests\StartOrderExtensionCheckoutRequest;
use App\Http\Requests\StartOrderTipCheckoutRequest;
use App\Models\Order;
use App\Services\Orders\RankTrackerActionService;
use App\Services\Payments\PaymentInitializationPipeline;
use App\Services\Payments\PaymentManager;
use App\Services\Payments\PendingCheckoutStore;
use App\Support\Logging\AppEventLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use LogicException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CustomerOrderActionController extends Controller
{
    public function __construct(
        protected PendingCheckoutStore $pendingCheckoutStore,
        protected PaymentInitializationPipeline $paymentInitializationPipeline,
        protected PaymentManager $paymentManager,
        protected RankTrackerActionService $rankTrackerActionService,
        protected AppEventLogger $eventLogger,
    ) {}

    public function startExtensionCheckout(StartOrderExtensionCheckoutRequest $request, Order $order): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && $user->role === 'customer', 403);

        try {
            $preparedCheckout = $this->rankTrackerActionService->buildExtensionCheckout($user, $order, $request->validated());
        } catch (ValidationException $exception) {
            return $this->redirectWithErrors($order, $exception->errors(), 'extendBoostModal', true);
        } catch (HttpException $exception) {
            abort($exception->getStatusCode(), $exception->getMessage());
        }

        return $this->launchCheckout($order, $user->id, $preparedCheckout, 'extendBoostModal');
    }

    public function pause(Request $request, Order $order): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && $user->role === 'customer', 403);

        try {
            $this->rankTrackerActionService->pause($user, $order);
        } catch (ValidationException $exception) {
            return $this->redirectWithErrors($order, $exception->errors());
        } catch (HttpException $exception) {
            if ($exception->getStatusCode() === 403) {
                abort(403, $exception->getMessage());
            }

            return $this->redirectWithErrors($order, [
                'order' => [$exception->getMessage()],
            ], 'pauseBoostModal');
        }
        $this->eventLogger->order('order.customer_paused', $order->fresh(), $user, [], $request);

        return redirect()
            ->route('user-chats.show', ['order' => $order])
            ->with('status', 'Boost paused.');
    }

    public function resume(Request $request, Order $order): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && $user->role === 'customer', 403);

        try {
            $this->rankTrackerActionService->resume($user, $order);
        } catch (ValidationException $exception) {
            return $this->redirectWithErrors($order, $exception->errors());
        } catch (HttpException $exception) {
            if ($exception->getStatusCode() === 403) {
                abort(403, $exception->getMessage());
            }

            return $this->redirectWithErrors($order, [
                'order' => [$exception->getMessage()],
            ], 'pauseBoostModal');
        }
        $this->eventLogger->order('order.customer_resumed', $order->fresh(), $user, [], $request);

        return redirect()
            ->route('user-chats.show', ['order' => $order])
            ->with('status', 'Boost resumed.');
    }

    public function startBoosterTipCheckout(StartOrderTipCheckoutRequest $request, Order $order): RedirectResponse
    {
        return $this->startTipCheckout($request, $order, 'tipBoosterModal', \App\Models\OrderTip::RECIPIENT_BOOSTER);
    }

    public function startAdminTipCheckout(StartOrderTipCheckoutRequest $request, Order $order): RedirectResponse
    {
        return $this->startTipCheckout($request, $order, 'tipAdminModal', \App\Models\OrderTip::RECIPIENT_ADMIN);
    }

    protected function startTipCheckout(StartOrderTipCheckoutRequest $request, Order $order, string $modalId, string $recipientType): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && $user->role === 'customer', 403);

        try {
            $preparedCheckout = $this->rankTrackerActionService->buildTipCheckout($user, $order, $request->validated(), $recipientType);
        } catch (ValidationException $exception) {
            return $this->redirectWithErrors($order, $exception->errors(), $modalId, true);
        } catch (HttpException $exception) {
            abort($exception->getStatusCode(), $exception->getMessage());
        }

        return $this->launchCheckout($order, $user->id, $preparedCheckout, $modalId);
    }

    protected function launchCheckout(Order $order, int $userId, array $preparedCheckout, string $modalId): RedirectResponse
    {
        $checkoutData = $preparedCheckout['checkoutData'];
        $metadata = (array) ($preparedCheckout['metadata'] ?? []);
        $pendingCheckout = $this->pendingCheckoutStore->create($userId, $checkoutData);
        $pendingCheckout = $this->pendingCheckoutStore->put(
            $pendingCheckout->withMergedMetadata($metadata)
        );
        $this->eventLogger->payment('payment.initialization_started', [
            'user_id' => $userId,
            'provider' => $checkoutData->paymentMethod,
            'price_cents' => $checkoutData->priceCents,
            'checkout_reference' => $pendingCheckout->reference,
            'checkout_kind' => (string) ($metadata['checkoutKind'] ?? 'rank_tracker'),
            'order_id' => $order->id,
        ]);

        try {
            $result = $this->paymentInitializationPipeline->initialize($pendingCheckout, $checkoutData);
            $this->eventLogger->payment('payment.initialization_ready', [
                'user_id' => $userId,
                'provider' => $checkoutData->paymentMethod,
                'price_cents' => $checkoutData->priceCents,
                'checkout_reference' => $pendingCheckout->reference,
                'checkout_kind' => (string) ($metadata['checkoutKind'] ?? 'rank_tracker'),
                'order_id' => $order->id,
                'redirect_type' => $result->type,
            ]);
        } catch (ValidationException $exception) {
            return $this->redirectWithErrors($order, $exception->errors(), $modalId, true);
        } catch (LogicException $exception) {
            Log::channel('payments')->error('Rank tracker payment initialization hit a configuration error.', [
                'order_id' => $order->id,
                'user_id' => $userId,
                'checkout_reference' => $pendingCheckout->reference,
                'message' => $exception->getMessage(),
            ]);

            return $this->redirectWithErrors($order, [
                'payment' => [$exception->getMessage()],
            ], $modalId, true);
        } catch (\Throwable $exception) {
            Log::channel('payments')->error('Rank tracker payment initialization failed.', [
                'order_id' => $order->id,
                'user_id' => $userId,
                'checkout_reference' => $pendingCheckout->reference,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return $this->redirectWithErrors($order, [
                'payment' => ['We could not start this payment right now. Please try again in a moment.'],
            ], $modalId, true);
        }

        if ($result->type === 'route') {
            return redirect()->route($result->target, $result->metadata['parameters'] ?? []);
        }

        return redirect()->away($result->target);
    }

    protected function redirectWithErrors(Order $order, array $errors, ?string $modalId = null, bool $withInput = false): RedirectResponse
    {
        $redirect = redirect()
            ->route('user-chats.show', ['order' => $order])
            ->withErrors($errors);

        if ($modalId) {
            $redirect->with('rankTrackerModal', $modalId);
        }

        if ($withInput) {
            $redirect->withInput();
        }

        return $redirect;
    }
}
