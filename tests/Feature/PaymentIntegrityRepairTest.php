<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Services\Payments\OrderPaymentIdentifierRepairService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentIntegrityRepairTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_payment_identifiers_are_reconciled_without_deleting_orders(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique('orders_stripe_session_id_unique');
            $table->dropUnique('orders_payment_reference_unique');
        });

        $customer = User::factory()->create([
            'role' => 'customer',
            'account_status' => 'active',
        ]);

        $canonicalStripe = $this->makeOrder($customer, [
            'stripe_session_id' => 'cs_duplicate',
            'payment_reference' => 'pi_unique_one',
            'paid_at' => now()->subDay(),
        ]);
        $duplicateStripe = $this->makeOrder($customer, [
            'stripe_session_id' => 'cs_duplicate',
            'payment_reference' => 'pi_unique_two',
            'paid_at' => now(),
        ]);
        $unpaidStripeDuplicate = $this->makeOrder($customer, [
            'stripe_session_id' => 'cs_duplicate',
            'payment_reference' => 'pi_unique_three',
            'paid_at' => null,
        ]);

        $canonicalReference = $this->makeOrder($customer, [
            'stripe_session_id' => 'cs_unique_four',
            'payment_reference' => 'pi_duplicate',
            'paid_at' => now()->subHours(6),
        ]);
        $duplicateReference = $this->makeOrder($customer, [
            'stripe_session_id' => 'cs_unique_five',
            'payment_reference' => 'pi_duplicate',
            'paid_at' => now()->subHour(),
        ]);

        $summary = app(OrderPaymentIdentifierRepairService::class)->repair(false);

        $this->assertSame(2, $summary['stripe_session_id']);
        $this->assertSame(1, $summary['payment_reference']);

        $canonicalStripe->refresh();
        $duplicateStripe->refresh();
        $unpaidStripeDuplicate->refresh();
        $canonicalReference->refresh();
        $duplicateReference->refresh();

        $this->assertSame('cs_duplicate', $canonicalStripe->stripe_session_id);
        $this->assertNull($duplicateStripe->stripe_session_id);
        $this->assertNull($unpaidStripeDuplicate->stripe_session_id);
        $this->assertSame('pi_duplicate', $canonicalReference->payment_reference);
        $this->assertNull($duplicateReference->payment_reference);

        $this->assertSame($canonicalStripe->id, data_get($duplicateStripe->metadata, 'paymentIntegrity.duplicateIdentifiers.0.canonicalOrderId'));
        $this->assertSame($canonicalStripe->id, data_get($unpaidStripeDuplicate->metadata, 'paymentIntegrity.duplicateIdentifiers.0.canonicalOrderId'));
        $this->assertSame($canonicalReference->id, data_get($duplicateReference->metadata, 'paymentIntegrity.duplicateIdentifiers.0.canonicalOrderId'));
        $this->assertSame(5, Order::query()->count());
    }

    protected function makeOrder(User $customer, array $overrides = []): Order
    {
        return Order::query()->create(array_merge([
            'user_id' => $customer->id,
            'order_number' => (string) Str::orderedUuid(),
            'product' => 'Rank Boosting',
            'status' => 'Completed',
            'payment_status' => 'paid',
            'price_cents' => 2500,
            'booster_payout_rate' => 0.6,
            'booster_payout_cents' => 1500,
            'currency' => 'USD',
            'details' => [
                'order' => [
                    'orderType' => 'Rank Boosting',
                    'currentDivision' => 'Silver I',
                    'desiredDivision' => 'Silver III',
                ],
            ],
            'metadata' => [],
        ], $overrides));
    }
}
