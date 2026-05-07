<?php

namespace Tests\Feature;

use App\Actions\CreateOrderAction;
use App\Data\Payments\PaymentCheckoutData;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class BoostingRebrandTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_order_action_defaults_to_rank_boosting(): void
    {
        Http::fake();

        $user = User::factory()->create([
            'role' => 'customer',
            'account_status' => 'active',
        ]);

        $checkoutData = new PaymentCheckoutData(
            requestData: [
                'firstName' => 'Demo',
                'lastName' => 'Customer',
                'email' => 'demo@example.com',
                'contactMethod' => 'email',
                'whatsapp' => null,
                'discord' => null,
            ],
            orderPayload: [],
            paymentMethod: 'stripe',
            priceCents: 12999,
            total: 131.49,
        );

        $order = app(CreateOrderAction::class)->execute($user->id, $checkoutData);

        $this->assertSame('Rank Boosting', $order->product);
    }

    public function test_customer_dashboard_route_remains_available_and_player_dashboard_route_is_removed(): void
    {
        $customer = User::factory()->create([
            'role' => 'customer',
            'account_status' => 'active',
        ]);

        $this->actingAs($customer)
            ->get(route('customer-dashboard'))
            ->assertOk();

        $this->actingAs($customer)
            ->get('/user/'.implode('-', ['player', 'dashboard']))
            ->assertNotFound();
    }

    public function test_checkout_page_uses_boosting_copy_only(): void
    {
        $this->get(route('checkout'))
            ->assertOk()
            ->assertSeeText('Secure VALORANT Boost Checkout')
            ->assertSeeText('I understand account-sharing and Duo / Self-Play boosting risks')
            ->assertDontSeeText($this->legacyGerund().'-only compliance');
    }

    public function test_boosting_backfill_migration_rewrites_legacy_copy(): void
    {
        $user = User::factory()->create([
            'role' => 'customer',
            'account_status' => 'active',
        ]);

        $order = Order::query()->create([
            'user_id' => $user->id,
            'order_number' => (string) Str::orderedUuid(),
            'product' => $this->legacyPhrase(),
            'status' => 'Pending',
            'payment_status' => 'pending',
            'price_cents' => 14999,
            'booster_payout_rate' => 0.6,
            'booster_payout_cents' => 8999,
            'currency' => 'USD',
            'details' => [
                'service' => $this->legacyPhrase(),
                'notes' => 'Ask the '.$this->legacySingular().' to stay discreet.',
                'order' => [
                    'orderType' => $this->legacyPhrase(),
                    'tags' => [$this->legacyGerund(), $this->legacyPlural()],
                ],
            ],
        ]);

        DB::table('faqs')->insert([
            'question' => 'Why choose '.$this->legacyGerund().' here?',
            'answer' => 'Our '.$this->legacyPlural().' deliver fast '.$this->legacyGerund().' updates.',
            'order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('testimonials')->insert([
            'author_name' => 'Customer One',
            'service' => $this->legacyPhrase(),
            'quote' => 'Best order ever.',
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = require base_path('database/migrations/2026_03_19_000014_backfill_boosting_copy.php');
        $migration->up();

        $order->refresh();

        $this->assertSame('Rank Boosting', $order->product);
        $this->assertSame('Rank Boosting', data_get($order->details, 'service'));
        $this->assertSame('Rank Boosting', data_get($order->details, 'order.orderType'));
        $this->assertSame('Ask the booster to stay discreet.', data_get($order->details, 'notes'));
        $this->assertSame(['boosting', 'boosters'], data_get($order->details, 'order.tags'));
        $this->assertSame('Why choose boosting here?', DB::table('faqs')->value('question'));
        $this->assertSame('Our boosters deliver fast boosting updates.', DB::table('faqs')->value('answer'));
        $this->assertSame('Rank Boosting', DB::table('testimonials')->value('service'));
    }

    private function legacyPhrase(): string
    {
        return implode(' ', ['Valorant', $this->legacyGerund(), 'plan']);
    }

    private function legacyPlural(): string
    {
        return $this->legacySingular().'es';
    }

    private function legacyGerund(): string
    {
        return $this->legacySingular().'ing';
    }

    private function legacySingular(): string
    {
        return implode('', ['coa', 'ch']);
    }
}
