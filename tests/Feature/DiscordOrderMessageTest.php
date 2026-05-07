<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Notifications\Discord\OrderCreatedMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DiscordOrderMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_placement_matches_orders_include_previous_rank_matches_and_payout(): void
    {
        $order = $this->makeOrder([
            'orderType' => 'Placement Matches',
            'currentDivision' => 'Silver I',
            'numberOfPlacementGames' => 5,
        ], [
            'price_cents' => 15000,
            'booster_payout_cents' => 9000,
        ]);

        $fields = $this->fieldMap((new OrderCreatedMessage($order))->embeds()[0]);

        $this->assertSame($order->order_number, $fields['Order ID']);
        $this->assertSame('Silver I', $fields['Previous Rank']);
        $this->assertSame('5', $fields['No. of Placement Matches']);
        $this->assertSame('$90.00', $fields['Payout']);
        $this->assertArrayNotHasKey('Current Rank', $fields);
        $this->assertArrayNotHasKey('List of Addons', $fields);
    }

    public function test_ranked_wins_orders_include_current_rank_wins_and_payout(): void
    {
        $order = $this->makeOrder([
            'orderType' => 'Ranked Wins',
            'currentDivision' => 'Diamond I',
            'numberOfWins' => 4,
        ], [
            'price_cents' => 20000,
            'booster_payout_cents' => 12450,
        ]);

        $fields = $this->fieldMap((new OrderCreatedMessage($order))->embeds()[0]);

        $this->assertSame($order->order_number, $fields['Order ID']);
        $this->assertSame('Diamond I', $fields['Current Rank']);
        $this->assertSame('4', $fields['No. of Ranked Wins']);
        $this->assertSame('$124.50', $fields['Payout']);
        $this->assertArrayNotHasKey('Desired Rank', $fields);
        $this->assertArrayNotHasKey('List of Addons', $fields);
    }

    public function test_rank_boosting_orders_include_ranks_addons_and_payout(): void
    {
        $order = $this->makeOrder([
            'orderType' => 'Rank Boosting',
            'currentDivision' => 'Gold II',
            'desiredDivision' => 'Platinum II',
            'addons' => ['Express Order', 'Solo-Queue Only'],
        ], [
            'price_cents' => 28000,
            'booster_payout_cents' => 16800,
        ]);

        $fields = $this->fieldMap((new OrderCreatedMessage($order))->embeds()[0]);

        $this->assertSame($order->order_number, $fields['Order ID']);
        $this->assertSame('Gold II', $fields['Current Rank']);
        $this->assertSame('Platinum II', $fields['Desired Rank']);
        $this->assertSame('Express Order, Solo-Queue Only', $fields['List of Addons']);
        $this->assertSame('$168.00', $fields['Payout']);
    }

    public function test_radiant_boost_orders_include_ranks_addon_fallback_and_payout(): void
    {
        $order = $this->makeOrder([
            'orderType' => 'Radiant Boost',
            'currentDivision' => 'Immortal II',
            'desiredDivision' => 'Radiant',
            'addons' => [],
        ], [
            'price_cents' => 50000,
            'booster_payout_cents' => 30000,
        ]);

        $fields = $this->fieldMap((new OrderCreatedMessage($order))->embeds()[0]);

        $this->assertSame($order->order_number, $fields['Order ID']);
        $this->assertSame('Immortal II', $fields['Current Rank']);
        $this->assertSame('Radiant', $fields['Desired Rank']);
        $this->assertSame('None', $fields['List of Addons']);
        $this->assertSame('$300.00', $fields['Payout']);
    }

    public function test_unexpected_order_types_fall_back_to_amount_without_breaking_the_embed(): void
    {
        $order = $this->makeOrder([
            'orderType' => 'Coaching Session',
        ], [
            'price_cents' => 8500,
            'booster_payout_cents' => 5100,
        ]);

        $fields = $this->fieldMap((new OrderCreatedMessage($order))->embeds()[0]);

        $this->assertSame($order->order_number, $fields['Order ID']);
        $this->assertSame('$85.00', $fields['Amount']);
        $this->assertSame('$51.00', $fields['Payout']);
        $this->assertArrayNotHasKey('Current Rank', $fields);
        $this->assertArrayNotHasKey('Previous Rank', $fields);
        $this->assertArrayNotHasKey('List of Addons', $fields);
    }

    protected function makeOrder(array $orderPayload, array $overrides = []): Order
    {
        $customer = User::factory()->create([
            'name' => 'Discord Customer',
            'role' => 'customer',
            'account_status' => 'active',
        ]);

        $order = Order::query()->create(array_merge([
            'user_id' => $customer->id,
            'order_number' => (string) Str::orderedUuid(),
            'product' => (string) ($orderPayload['orderType'] ?? 'Rank Boosting'),
            'status' => 'Pending',
            'payment_status' => 'paid',
            'price_cents' => 19999,
            'booster_payout_rate' => 60,
            'booster_payout_cents' => 12000,
            'currency' => 'USD',
            'details' => [
                'service' => (string) ($orderPayload['orderType'] ?? 'Rank Boosting'),
                'addons' => $orderPayload['addons'] ?? [],
                'order' => $orderPayload,
            ],
            'metadata' => [
                'customer' => [
                    'firstName' => 'Discord',
                    'lastName' => 'Customer',
                    'email' => $customer->email,
                ],
            ],
        ], $overrides));

        return $order->fresh('user');
    }

    /**
     * @return array<string, string>
     */
    protected function fieldMap(array $embed): array
    {
        return collect($embed['fields'] ?? [])
            ->mapWithKeys(fn (array $field): array => [$field['name'] => (string) $field['value']])
            ->all();
    }
}
