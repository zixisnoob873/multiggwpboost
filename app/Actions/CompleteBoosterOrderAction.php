<?php

namespace App\Actions;

use App\Models\Order;
use App\Models\User;
use App\Services\Orders\OrderProgressService;
use App\Support\Logging\AppEventLogger;
use App\Support\OrderLifecycleMetadata;
use App\Support\OrderStatus;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CompleteBoosterOrderAction
{
    public function __construct(
        protected OrderProgressService $orderProgressService,
        protected AppEventLogger $eventLogger,
    ) {}

    public function execute(User $booster, Order $order): Order
    {
        return DB::transaction(function () use ($booster, $order) {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->getKey());

            if ((int) $lockedOrder->booster_id !== (int) $booster->getKey()) {
                throw new HttpException(403, 'Only the assigned booster can complete this order.');
            }

            if (! in_array($lockedOrder->status, [OrderStatus::IN_PROGRESS, OrderStatus::PAUSED], true)) {
                throw new HttpException(422, 'Only in-progress or paused assigned orders can be completed.');
            }

            if (! is_string($lockedOrder->completion_proof_path) || trim($lockedOrder->completion_proof_path) === '') {
                throw new HttpException(422, 'Upload a completion screenshot before marking the order as completed.');
            }

            $progressPayload = $this->orderProgressService->completionPayload($lockedOrder, $booster);
            $details = $this->orderProgressService->applyProgressPayload($lockedOrder, $progressPayload);
            $metadata = is_array($lockedOrder->metadata) ? $lockedOrder->metadata : [];
            $metadata = OrderLifecycleMetadata::record($metadata, 'completed', $lockedOrder->status, OrderStatus::COMPLETED, [
                'source' => 'booster',
                'actor_id' => $booster->getKey(),
                'next_step' => 'Review the completed order in your dashboard.',
                'completion' => [
                    'proof_uploaded_at' => $lockedOrder->completion_proof_uploaded_at?->toIso8601String(),
                    'proof_available' => true,
                ],
            ]);

            $lockedOrder->forceFill([
                'status' => OrderStatus::COMPLETED,
                'details' => $details,
                'metadata' => $metadata,
                'completed_at' => $lockedOrder->completed_at ?? now(),
                'completed_by_booster_id' => $booster->getKey(),
            ])->save();

            $completedOrder = $lockedOrder->refresh();
            $this->eventLogger->order('order.booster_completed', $completedOrder, $booster);

            return $completedOrder;
        }, 3);
    }
}
