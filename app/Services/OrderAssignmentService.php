<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use App\Services\Mail\BoosterEmailNotifier;
use App\Support\OrderLifecycleMetadata;
use App\Support\OrderStatus;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class OrderAssignmentService
{
    public function __construct(protected BoosterEmailNotifier $boosterEmailNotifier) {}

    public function claim(User $booster, Order $order): Order
    {
        return DB::transaction(function () use ($booster, $order) {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->getKey());

            if (! OrderStatus::canBeClaimed($lockedOrder->status, $lockedOrder->booster_id)) {
                if ($lockedOrder->booster_id !== null) {
                    throw new HttpException(422, 'This order has already been claimed.');
                }

                throw new HttpException(422, 'Only pending unassigned orders can be claimed.');
            }

            $metadata = is_array($lockedOrder->metadata) ? $lockedOrder->metadata : [];
            $metadata = OrderLifecycleMetadata::record(
                $metadata,
                'assigned',
                $lockedOrder->status,
                OrderStatus::IN_PROGRESS,
                [
                    'source' => 'booster_claim',
                    'actor_id' => $booster->getKey(),
                    'next_step' => 'Work is underway in the order dashboard.',
                ]
            );

            $lockedOrder->forceFill([
                'booster_id' => $booster->id,
                'status' => OrderStatus::IN_PROGRESS,
                'assigned_at' => now(),
                'metadata' => $metadata,
            ])->save();

            return $lockedOrder->refresh()->load('booster');
        }, 3);
    }

    public function assignByAdmin(Order $order, ?int $boosterId, ?string $explicitStatus = null): Order
    {
        return DB::transaction(function () use ($order, $boosterId, $explicitStatus) {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->getKey());
            $previousBoosterId = $lockedOrder->booster_id;

            $attributes = $this->adminAssignmentAttributes($lockedOrder, $boosterId, $explicitStatus);
            $nextStatus = (string) ($attributes['status'] ?? $lockedOrder->status);

            if ($lockedOrder->status !== $nextStatus) {
                $event = OrderLifecycleMetadata::eventKey($lockedOrder->status, $nextStatus);

                if ($event !== null) {
                    $metadata = is_array($lockedOrder->metadata) ? $lockedOrder->metadata : [];
                    $attributes['metadata'] = OrderLifecycleMetadata::record(
                        $metadata,
                        $event,
                        $lockedOrder->status,
                        $nextStatus,
                        [
                            'source' => 'admin_assignment',
                            'next_step' => 'Work is underway in the order dashboard.',
                        ]
                    );
                }
            }

            $lockedOrder->forceFill($attributes)->save();

            $updatedOrder = $lockedOrder->refresh()->load(['user', 'booster']);

            if ($updatedOrder->booster_id !== null && (int) $previousBoosterId !== (int) $updatedOrder->booster_id) {
                $this->boosterEmailNotifier->queueOrderAssignedByAdmin($updatedOrder);
            }

            return $updatedOrder;
        }, 3);
    }

    public function releaseToQueue(Order $order, User $booster): Order
    {
        return DB::transaction(function () use ($order, $booster) {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->getKey());

            if ((int) $lockedOrder->booster_id !== (int) $booster->id) {
                throw new HttpException(403, 'Only the assigned booster can drop this order.');
            }

            if (! $lockedOrder->canBoosterDrop()) {
                throw new HttpException(422, 'Completed and cancelled orders can no longer be dropped.');
            }

            $lockedOrder->forceFill([
                'booster_id' => null,
                'assigned_at' => null,
                'status' => OrderStatus::PENDING,
                'completion_proof_path' => null,
                'completion_proof_uploaded_at' => null,
            ])->save();

            return $lockedOrder->refresh()->load('booster');
        }, 3);
    }

    public function adminAssignmentAttributes(Order $order, ?int $boosterId, ?string $explicitStatus = null): array
    {
        $status = $explicitStatus;

        if ($status === null) {
            if ($boosterId && $order->status === OrderStatus::PENDING) {
                $status = OrderStatus::IN_PROGRESS;
            } elseif (! $boosterId && in_array($order->status, [OrderStatus::IN_PROGRESS, OrderStatus::PAUSED], true)) {
                $status = OrderStatus::PENDING;
            }
        } elseif ($boosterId && $status === OrderStatus::PENDING) {
            $status = OrderStatus::IN_PROGRESS;
        } elseif (! $boosterId && in_array($status, [OrderStatus::IN_PROGRESS, OrderStatus::PAUSED], true)) {
            $status = OrderStatus::PENDING;
        }

        $attributes = [
            'booster_id' => $boosterId,
            'assigned_at' => $boosterId ? ($order->assigned_at ?? now()) : null,
        ];

        if ($status !== null) {
            $attributes['status'] = $status;
        }

        return $attributes;
    }
}
