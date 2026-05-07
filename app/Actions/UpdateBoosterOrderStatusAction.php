<?php

namespace App\Actions;

use App\Models\Order;
use App\Models\User;
use App\Support\Logging\AppEventLogger;
use App\Support\OrderLifecycleMetadata;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class UpdateBoosterOrderStatusAction
{
    public function __construct(protected AppEventLogger $eventLogger) {}

    public function execute(User $user, Order $order, string $status): Order
    {
        return DB::transaction(function () use ($user, $order, $status) {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->getKey());

            if ((int) $lockedOrder->booster_id !== (int) $user->id) {
                throw new HttpException(403, 'You are not allowed to update this order.');
            }

            if (! $lockedOrder->canBoosterUpdateStatusTo($status)) {
                throw new HttpException(422, 'Boosters can only start assigned pending orders from this control.');
            }

            $previousStatus = (string) $lockedOrder->status;
            $metadata = is_array($lockedOrder->metadata) ? $lockedOrder->metadata : [];
            $event = OrderLifecycleMetadata::eventKey($previousStatus, $status);

            if ($event !== null) {
                $metadata = OrderLifecycleMetadata::record($metadata, $event, $previousStatus, $status, [
                    'source' => 'booster',
                    'actor_id' => $user->getKey(),
                    'next_step' => 'Work is underway in the order dashboard.',
                ]);
            }

            $lockedOrder->forceFill([
                'status' => $status,
                'metadata' => $metadata,
            ])->save();

            $updatedOrder = $lockedOrder->refresh();
            $this->eventLogger->order('order.booster_status_updated', $updatedOrder, $user, [
                'previous_status' => $previousStatus,
                'next_status' => $status,
            ]);

            return $updatedOrder;
        }, 3);
    }
}
