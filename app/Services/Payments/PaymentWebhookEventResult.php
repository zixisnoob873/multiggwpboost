<?php

namespace App\Services\Payments;

use App\Models\PaymentWebhookEvent;

class PaymentWebhookEventResult
{
    public function __construct(
        public readonly PaymentWebhookEvent $event,
        public readonly string $action,
    ) {}

    public function shouldProcess(): bool
    {
        return $this->action === 'process';
    }

    public function alreadyHandled(): bool
    {
        return $this->action === 'already_handled';
    }

    public function alreadyProcessing(): bool
    {
        return $this->action === 'already_processing';
    }
}
