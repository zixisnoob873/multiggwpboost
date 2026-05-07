<?php

namespace App\Console\Commands;

use App\Services\Payments\OrderPaymentIdentifierRepairService;
use Illuminate\Console\Command;

class RepairOrderPaymentIdentifiersCommand extends Command
{
    protected $signature = 'payments:repair-order-identifiers';

    protected $description = 'Repair duplicate Stripe session IDs and payment references before enforcing uniqueness.';

    public function handle(OrderPaymentIdentifierRepairService $repairService): int
    {
        $summary = $repairService->repair();

        $this->table(['Identifier', 'Reconciled duplicates'], [
            ['stripe_session_id', $summary['stripe_session_id']],
            ['payment_reference', $summary['payment_reference']],
        ]);

        return self::SUCCESS;
    }
}
