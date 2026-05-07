<?php

namespace App\Services\Payments;

use App\Models\Order;
use Illuminate\Support\Facades\Log;

class OrderPaymentIdentifierRepairService
{
    public function repair(bool $log = true): array
    {
        $summary = [
            'stripe_session_id' => $this->repairColumn('stripe_session_id'),
            'payment_reference' => $this->repairColumn('payment_reference'),
        ];

        if ($log && ($summary['stripe_session_id'] > 0 || $summary['payment_reference'] > 0)) {
            Log::channel('payments')->warning('Reconciled duplicate order payment identifiers before enforcing uniqueness.', $summary);
        }

        return $summary;
    }

    protected function repairColumn(string $column): int
    {
        $duplicateValues = Order::query()
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->groupBy($column)
            ->havingRaw('COUNT(*) > 1')
            ->pluck($column);

        $reconciled = 0;

        foreach ($duplicateValues as $value) {
            $orders = Order::query()
                ->where($column, $value)
                ->orderByRaw('CASE WHEN paid_at IS NULL THEN 1 ELSE 0 END')
                ->orderBy('paid_at')
                ->orderBy('created_at')
                ->orderBy('id')
                ->get();

            $canonical = $orders->first();

            foreach ($orders->skip(1) as $duplicate) {
                $metadata = is_array($duplicate->metadata) ? $duplicate->metadata : [];
                $integrity = is_array($metadata['paymentIntegrity'] ?? null) ? $metadata['paymentIntegrity'] : [];
                $duplicateIdentifiers = is_array($integrity['duplicateIdentifiers'] ?? null) ? $integrity['duplicateIdentifiers'] : [];
                $duplicateIdentifiers[] = [
                    'field' => $column,
                    'original' => $value,
                    'canonicalOrderId' => $canonical?->id,
                    'reconciledAt' => now()->toIso8601String(),
                    'strategy' => 'preserve_canonical_null_duplicate_identifier',
                ];
                $integrity['duplicateIdentifiers'] = $duplicateIdentifiers;
                $metadata['paymentIntegrity'] = $integrity;

                $duplicate->forceFill([
                    $column => null,
                    'metadata' => $metadata,
                ])->save();

                $reconciled++;
            }
        }

        return $reconciled;
    }
}
