<?php

namespace App\Enums;

use App\Mail\CustomerOrders\OrderAssignedCustomerMail;
use App\Mail\CustomerOrders\OrderCancelledCustomerMail;
use App\Mail\CustomerOrders\OrderCompletedCustomerMail;
use App\Mail\CustomerOrders\OrderCreatedCustomerMail;
use App\Mail\CustomerOrders\OrderPausedCustomerMail;
use App\Mail\CustomerOrders\OrderRefundedCustomerMail;
use App\Mail\CustomerOrders\OrderResumedCustomerMail;
use App\Support\OrderStatus;

enum CustomerOrderEmailType: string
{
    case CREATED = 'order_created';
    case ASSIGNED = 'order_assigned';
    case PAUSED = 'order_paused';
    case CANCELLED = 'order_cancelled';
    case REFUNDED = 'order_refunded';
    case RESUMED = 'order_resumed';
    case COMPLETED = 'order_completed';

    public static function fromStatusChange(?string $previousStatus, ?string $nextStatus): ?self
    {
        if ($previousStatus === $nextStatus) {
            return null;
        }

        return match ($nextStatus) {
            OrderStatus::IN_PROGRESS => $previousStatus === OrderStatus::PAUSED ? self::RESUMED : self::ASSIGNED,
            OrderStatus::PAUSED => self::PAUSED,
            OrderStatus::CANCELLED => self::CANCELLED,
            OrderStatus::REFUNDED => self::REFUNDED,
            OrderStatus::COMPLETED => self::COMPLETED,
            default => null,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::CREATED => 'Order Created',
            self::ASSIGNED => 'Order Assigned',
            self::PAUSED => 'Order Paused',
            self::CANCELLED => 'Order Cancelled',
            self::REFUNDED => 'Order Refunded',
            self::RESUMED => 'Order Resumed',
            self::COMPLETED => 'Order Completed',
        };
    }

    public function mailableClass(): string
    {
        return match ($this) {
            self::CREATED => OrderCreatedCustomerMail::class,
            self::ASSIGNED => OrderAssignedCustomerMail::class,
            self::PAUSED => OrderPausedCustomerMail::class,
            self::CANCELLED => OrderCancelledCustomerMail::class,
            self::REFUNDED => OrderRefundedCustomerMail::class,
            self::RESUMED => OrderResumedCustomerMail::class,
            self::COMPLETED => OrderCompletedCustomerMail::class,
        };
    }
}
