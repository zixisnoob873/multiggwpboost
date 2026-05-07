<?php

namespace Tests\Unit;

use App\Enums\CustomerOrderEmailType;
use App\Support\OrderStatus;
use PHPUnit\Framework\TestCase;

class CustomerOrderEmailTypeTest extends TestCase
{
    public function test_in_progress_transitions_are_mapped_to_assigned_or_resumed(): void
    {
        $this->assertSame(
            CustomerOrderEmailType::ASSIGNED,
            CustomerOrderEmailType::fromStatusChange(OrderStatus::PENDING, OrderStatus::IN_PROGRESS)
        );

        $this->assertSame(
            CustomerOrderEmailType::RESUMED,
            CustomerOrderEmailType::fromStatusChange(OrderStatus::PAUSED, OrderStatus::IN_PROGRESS)
        );
    }

    public function test_supported_status_changes_map_to_customer_transactional_email_types(): void
    {
        $this->assertSame(
            CustomerOrderEmailType::PAUSED,
            CustomerOrderEmailType::fromStatusChange(OrderStatus::IN_PROGRESS, OrderStatus::PAUSED)
        );

        $this->assertSame(
            CustomerOrderEmailType::CANCELLED,
            CustomerOrderEmailType::fromStatusChange(OrderStatus::IN_PROGRESS, OrderStatus::CANCELLED)
        );

        $this->assertSame(
            CustomerOrderEmailType::REFUNDED,
            CustomerOrderEmailType::fromStatusChange(OrderStatus::COMPLETED, OrderStatus::REFUNDED)
        );

        $this->assertSame(
            CustomerOrderEmailType::COMPLETED,
            CustomerOrderEmailType::fromStatusChange(OrderStatus::IN_PROGRESS, OrderStatus::COMPLETED)
        );

        $this->assertNull(
            CustomerOrderEmailType::fromStatusChange(OrderStatus::IN_PROGRESS, OrderStatus::PENDING)
        );
    }
}
