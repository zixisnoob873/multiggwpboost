<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Support\OrderStatus;
use App\Support\Pricing\ValorantPricingEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class BoosterWorkspaceFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_claim_orders_page_renders_and_seeds_session_captcha_codes(): void
    {
        $booster = $this->makeUser('booster');
        $customer = $this->makeUser('customer');
        $order = $this->makeOrderForService($customer, null, 'Rank Boosting', status: OrderStatus::PENDING);

        $response = $this->actingAs($booster)
            ->get(route('booster-claim-orders'));

        $response->assertOk();
        $response->assertSessionHas('booster_claim_captcha_codes', function ($codes) use ($order): bool {
            $captcha = data_get($codes, (string) $order->id);

            return is_array($codes)
                && is_string($captcha)
                && preg_match('/^\d{4}$/', $captcha) === 1;
        });
    }

    public function test_assigned_booster_can_drop_order_after_captcha_and_order_returns_to_pending_queue(): void
    {
        $customer = $this->makeUser('customer');
        $booster = $this->makeUser('booster');
        $order = $this->makeOrderForService($customer, $booster, 'Rank Boosting');

        $response = $this->actingAs($booster)
            ->withSession([
                'booster_drop_captcha_codes' => [$order->id => '1234'],
            ])
            ->post(route('booster-orders.drop', $order), [
                'drop_captcha' => '1234',
            ]);

        $response->assertRedirect(route('booster-claim-orders'));

        $order->refresh();

        $this->assertNull($order->booster_id);
        $this->assertNull($order->assigned_at);
        $this->assertSame(OrderStatus::PENDING, $order->status);
    }

    public function test_non_assigned_booster_cannot_drop_the_order(): void
    {
        $customer = $this->makeUser('customer');
        $assignedBooster = $this->makeUser('booster');
        $otherBooster = $this->makeUser('booster');
        $order = $this->makeOrderForService($customer, $assignedBooster, 'Rank Boosting');

        $this->actingAs($otherBooster)
            ->withSession([
                'booster_drop_captcha_codes' => [$order->id => '1234'],
            ])
            ->post(route('booster-orders.drop', $order), [
                'drop_captcha' => '1234',
            ])
            ->assertForbidden();
    }

    public function test_rank_boost_progress_is_computed_from_rank_and_rr(): void
    {
        $customer = $this->makeUser('customer');
        $booster = $this->makeUser('booster');
        $order = $this->makeOrderForService($customer, $booster, 'Rank Boosting');

        $response = $this->actingAs($booster)
            ->patch(route('orders.progress.update', $order), [
                'current_rank' => 'Bronze III',
                'current_rr' => 0,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('status', 'Progress updated.');

        $order->refresh();

        $this->assertSame('Bronze III', data_get($order->details, 'progress.currentRank'));
        $this->assertSame(0, data_get($order->details, 'progress.currentRR'));
        $this->assertSame(50, (int) data_get($order->details, 'progress.pct'));
    }

    public function test_ranked_wins_progress_is_computed_and_rejects_values_above_the_purchased_total(): void
    {
        $customer = $this->makeUser('customer');
        $booster = $this->makeUser('booster');
        $order = $this->makeOrderForService($customer, $booster, 'Ranked Wins');

        $this->actingAs($booster)
            ->patch(route('orders.progress.update', $order), [
                'completed_wins' => 4,
            ])
            ->assertRedirect();

        $order->refresh();
        $this->assertSame(4, (int) data_get($order->details, 'progress.completedWins'));
        $this->assertSame(40, (int) data_get($order->details, 'progress.pct'));

        $this->actingAs($booster)
            ->from(route('booster-chats.show', ['order' => $order]))
            ->patch(route('orders.progress.update', $order), [
                'completed_wins' => 99,
            ])
            ->assertRedirect(route('booster-chats.show', ['order' => $order]))
            ->assertSessionHasErrors('completed_wins');

        $order->refresh();
        $this->assertSame(4, (int) data_get($order->details, 'progress.completedWins'));
        $this->assertSame(40, (int) data_get($order->details, 'progress.pct'));
    }

    public function test_placements_progress_is_computed_from_completed_matches(): void
    {
        $customer = $this->makeUser('customer');
        $booster = $this->makeUser('booster');
        $order = $this->makeOrderForService($customer, $booster, 'Placement Matches');

        $this->actingAs($booster)
            ->patch(route('orders.progress.update', $order), [
                'completed_placements' => 3,
            ])
            ->assertRedirect();

        $order->refresh();

        $this->assertSame(3, (int) data_get($order->details, 'progress.completedPlacements'));
        $this->assertSame(60, (int) data_get($order->details, 'progress.pct'));
    }

    public function test_radiant_boost_progress_is_computed_from_rank_and_rr(): void
    {
        $customer = $this->makeUser('customer');
        $booster = $this->makeUser('booster');
        $order = $this->makeOrderForService($customer, $booster, 'Radiant Boost');

        $this->actingAs($booster)
            ->patch(route('orders.progress.update', $order), [
                'current_rank' => 'Immortal II',
                'current_rr' => 50,
            ])
            ->assertRedirect();

        $order->refresh();

        $this->assertSame('Immortal II', data_get($order->details, 'progress.currentRank'));
        $this->assertSame(50, data_get($order->details, 'progress.currentRR'));
        $this->assertSame(50, (int) data_get($order->details, 'progress.pct'));
    }

    public function test_booster_cannot_update_progress_on_an_order_they_do_not_own(): void
    {
        $customer = $this->makeUser('customer');
        $assignedBooster = $this->makeUser('booster');
        $otherBooster = $this->makeUser('booster');
        $order = $this->makeOrderForService($customer, $assignedBooster, 'Rank Boosting');

        $this->actingAs($otherBooster)
            ->patch(route('orders.progress.update', $order), [
                'current_rank' => 'Bronze III',
                'current_rr' => 0,
            ])
            ->assertForbidden();
    }

    public function test_completion_requires_proof_upload_before_captcha_confirmation(): void
    {
        $customer = $this->makeUser('customer');
        $booster = $this->makeUser('booster');
        $order = $this->makeOrderForService($customer, $booster, 'Rank Boosting');

        $response = $this->actingAs($booster)
            ->from(route('booster-chats.show', $order))
            ->withSession([
                'booster_complete_captcha_codes' => [$order->id => '1234'],
            ])
            ->post(route('booster-orders.complete', $order), [
                'complete_captcha' => '1234',
            ]);

        $response->assertRedirect(route('booster-chats.show', $order));
        $response->assertSessionHasErrors('complete');

        $order->refresh();
        $this->assertSame(OrderStatus::IN_PROGRESS, $order->status);
        $this->assertNull($order->completion_proof_path);
    }

    public function test_completion_captcha_is_required_after_proof_upload(): void
    {
        Storage::fake('local');

        $customer = $this->makeUser('customer');
        $booster = $this->makeUser('booster');
        $order = $this->makeOrderForService($customer, $booster, 'Rank Boosting');

        $proofResponse = $this->actingAs($booster)
            ->post(route('booster-orders.completion-proof.store', $order), [
                'completion_proof' => UploadedFile::fake()->image('proof.png', 1200, 800),
            ]);

        $proofResponse->assertRedirect(route('booster-chats.show', $order));
        $proofResponse->assertSessionHas('boosterModal', 'boosterCompleteCaptchaModal');

        $order->refresh();
        $this->assertNotNull($order->completion_proof_path);
        Storage::disk('local')->assertExists($order->completion_proof_path);
        $this->assertSame(OrderStatus::IN_PROGRESS, $order->status);

        $captchaResponse = $this->actingAs($booster)
            ->from(route('booster-chats.show', $order))
            ->withSession([
                'booster_complete_captcha_codes' => [$order->id => '1234'],
            ])
            ->post(route('booster-orders.complete', $order), [
                'complete_captcha' => '9999',
            ]);

        $captchaResponse->assertRedirect(route('booster-chats.show', $order));
        $captchaResponse->assertSessionHasErrors('complete_captcha');

        $order->refresh();
        $this->assertSame(OrderStatus::IN_PROGRESS, $order->status);
    }

    public function test_successful_completion_with_proof_and_captcha_marks_order_completed_and_admin_can_view_the_proof(): void
    {
        Storage::fake('local');

        $customer = $this->makeUser('customer');
        $booster = $this->makeUser('booster');
        $admin = $this->makeUser('admin');
        $order = $this->makeOrderForService($customer, $booster, 'Rank Boosting');

        $this->actingAs($booster)
            ->post(route('booster-orders.completion-proof.store', $order), [
                'completion_proof' => UploadedFile::fake()->image('proof.png', 1200, 800),
            ])
            ->assertRedirect(route('booster-chats.show', $order));

        $order->refresh();
        $storedProofPath = $order->completion_proof_path;

        $completeResponse = $this->actingAs($booster)
            ->withSession([
                'booster_complete_captcha_codes' => [$order->id => '1234'],
            ])
            ->post(route('booster-orders.complete', $order), [
                'complete_captcha' => '1234',
            ]);

        $completeResponse->assertRedirect(route('booster-orders', ['view' => 'all']));

        $order->refresh();

        $this->assertSame(OrderStatus::COMPLETED, $order->status);
        $this->assertNotNull($order->completed_at);
        $this->assertSame($booster->id, $order->completed_by_booster_id);
        $this->assertSame(100, (int) data_get($order->details, 'progress.pct'));
        $this->assertSame('Silver III', data_get($order->details, 'progress.currentRank'));
        Storage::disk('local')->assertExists($storedProofPath);

        $proofUrl = route('admin-orders.completion-proof', $order);

        $this->actingAs($admin)
            ->get(route('admin-chats.show', $order))
            ->assertOk()
            ->assertSee($proofUrl, false);

        $this->actingAs($admin)
            ->get($proofUrl)
            ->assertOk();
    }

    public function test_booster_cannot_complete_an_order_they_do_not_own(): void
    {
        Storage::fake('local');

        $customer = $this->makeUser('customer');
        $assignedBooster = $this->makeUser('booster');
        $otherBooster = $this->makeUser('booster');
        $order = $this->makeOrderForService($customer, $assignedBooster, 'Rank Boosting');

        $this->actingAs($otherBooster)
            ->post(route('booster-orders.completion-proof.store', $order), [
                'completion_proof' => UploadedFile::fake()->image('proof.png', 1200, 800),
            ])
            ->assertForbidden();

        $this->actingAs($otherBooster)
            ->withSession([
                'booster_complete_captcha_codes' => [$order->id => '1234'],
            ])
            ->post(route('booster-orders.complete', $order), [
                'complete_captcha' => '1234',
            ])
            ->assertForbidden();
    }

    public function test_already_completed_orders_cannot_be_completed_again_and_boosters_cannot_access_them_directly(): void
    {
        Storage::fake('local');

        $customer = $this->makeUser('customer');
        $booster = $this->makeUser('booster');
        $order = $this->makeOrderForService($customer, $booster, 'Rank Boosting', status: OrderStatus::COMPLETED, overrides: [
            'completion_proof_path' => 'order-completion-proofs/'.$customer->id.'/proof.png',
            'completion_proof_uploaded_at' => now(),
            'completed_at' => now(),
            'completed_by_booster_id' => $booster->id,
            'details' => [
                'progress' => [
                    'pct' => 100,
                    'currentRank' => 'Silver III',
                    'currentRR' => 0,
                ],
            ],
        ]);

        Storage::disk('local')->put($order->completion_proof_path, 'proof');

        $this->actingAs($booster)
            ->get(route('booster-chats.show', $order))
            ->assertRedirect(route('booster-orders', ['view' => 'all']));

        $this->actingAs($booster)
            ->get(route('order-chat.messages.index', ['order' => $order, 'threadType' => 'customer_booster']))
            ->assertForbidden();

        $this->actingAs($booster)
            ->from(route('booster-orders'))
            ->withSession([
                'booster_complete_captcha_codes' => [$order->id => '1234'],
            ])
            ->post(route('booster-orders.complete', $order), [
                'complete_captcha' => '1234',
            ])
            ->assertRedirect(route('booster-orders'))
            ->assertSessionHasErrors('complete');
    }

    public function test_booster_dashboard_links_use_the_expected_filtered_routes(): void
    {
        $booster = $this->makeUser('booster');
        $customer = $this->makeUser('customer');
        $activeOrder = $this->makeOrderForService($customer, $booster, 'Rank Boosting');

        $response = $this->actingAs($booster)
            ->get(route('booster-dashboard'))
            ->assertOk()
            ->assertSee(route('booster-orders', ['view' => 'all']), false)
            ->assertSee(route('booster-orders', ['view' => 'assigned']), false)
            ->assertSee(route('booster-chats'), false)
            ->assertDontSee('Contact Admin');

        $response->assertSee(route('booster-chats.show', $activeOrder), false);
    }

    public function test_booster_orders_page_renders_service_specific_task_labels(): void
    {
        $booster = $this->makeUser('booster');
        $customer = $this->makeUser('customer');

        $rankBoostOrder = $this->makeOrderForService($customer, $booster, 'Rank Boosting');
        $rankedWinsOrder = $this->makeOrderForService($customer, $booster, 'Ranked Wins');
        $placementsOrder = $this->makeOrderForService($customer, $booster, 'Placement Matches');
        $radiantOrder = $this->makeOrderForService($customer, $booster, 'Radiant Boost');

        $this->actingAs($booster)
            ->get(route('booster-orders', ['view' => 'all']))
            ->assertOk()
            ->assertSee($rankBoostOrder->taskLabel())
            ->assertSee($rankedWinsOrder->taskLabel())
            ->assertSee($placementsOrder->taskLabel())
            ->assertSee($radiantOrder->taskLabel())
            ->assertSee('Task');
    }

    public function test_booster_orders_filters_apply_server_side(): void
    {
        $booster = $this->makeUser('booster');
        $customer = $this->makeUser('customer');

        $targetOrder = $this->makeOrderForService($customer, $booster, 'Rank Boosting');
        $naOrder = $this->makeOrderForService($customer, $booster, 'Placement Matches');
        $pausedWinsOrder = $this->makeOrderForService($customer, $booster, 'Ranked Wins', status: OrderStatus::PAUSED);
        $completedOrder = $this->makeOrderForService($customer, $booster, 'Radiant Boost', status: OrderStatus::COMPLETED);

        $this->actingAs($booster)
            ->get(route('booster-orders', ['view' => 'assigned', 'status' => OrderStatus::IN_PROGRESS]))
            ->assertOk()
            ->assertSee($targetOrder->order_number)
            ->assertSee($naOrder->order_number)
            ->assertDontSee($pausedWinsOrder->order_number)
            ->assertDontSee($completedOrder->order_number);

        $this->actingAs($booster)
            ->get(route('booster-orders', ['view' => 'all', 'region' => 'EU']))
            ->assertOk()
            ->assertSee($targetOrder->order_number)
            ->assertSee($pausedWinsOrder->order_number)
            ->assertSee($completedOrder->order_number)
            ->assertDontSee($naOrder->order_number);

        $this->actingAs($booster)
            ->get(route('booster-orders', ['view' => 'all', 'service' => 'Placement Matches']))
            ->assertOk()
            ->assertSee($naOrder->order_number)
            ->assertDontSee($targetOrder->order_number)
            ->assertDontSee($pausedWinsOrder->order_number);

        $this->actingAs($booster)
            ->get(route('booster-orders', ['view' => 'all', 'search' => $completedOrder->order_number]))
            ->assertOk()
            ->assertSee($completedOrder->order_number)
            ->assertDontSee($targetOrder->order_number);
    }

    public function test_booster_orders_filters_reject_invalid_query_values(): void
    {
        $booster = $this->makeUser('booster');

        $this->actingAs($booster)
            ->get(route('booster-orders', [
                'view' => 'legacy',
                'status' => 'Broken',
                'region' => 'Mars',
                'service' => 'Unknown Boost',
            ]))
            ->assertRedirect(route('booster-orders'))
            ->assertSessionHasErrors(['view', 'status', 'region', 'service']);
    }

    public function test_booster_chats_index_lists_only_active_assigned_orders(): void
    {
        $booster = $this->makeUser('booster');
        $otherBooster = $this->makeUser('booster');
        $customer = $this->makeUser('customer');

        $activeOrder = $this->makeOrderForService($customer, $booster, 'Rank Boosting');
        $pausedOrder = $this->makeOrderForService($customer, $booster, 'Ranked Wins', status: OrderStatus::PAUSED);
        $completedOrder = $this->makeOrderForService($customer, $booster, 'Placement Matches', status: OrderStatus::COMPLETED);
        $otherBoosterOrder = $this->makeOrderForService($customer, $otherBooster, 'Radiant Boost');

        $response = $this->actingAs($booster)
            ->get(route('booster-chats'))
            ->assertOk()
            ->assertSee($activeOrder->order_number)
            ->assertSee($pausedOrder->order_number)
            ->assertDontSee($completedOrder->order_number)
            ->assertDontSee($otherBoosterOrder->order_number)
            ->assertSee(route('booster-chats.show', $activeOrder), false)
            ->assertSee(route('booster-chats.show', $pausedOrder), false);

        $response->assertSee('Active Orders');
    }

    public function test_booster_wallet_page_keeps_the_adjustments_table_without_duplicating_the_heading(): void
    {
        $booster = $this->makeUser('booster');

        $html = $this->actingAs($booster)
            ->get(route('booster-wallet'))
            ->assertOk()
            ->getContent();

        $this->assertSame(1, substr_count($html, 'Wallet Adjustments'));
        $this->assertStringContainsString('Request Withdrawal', $html);
    }

    protected function makeUser(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'account_status' => 'active',
        ]);
    }

    protected function makeOrderForService(
        User $customer,
        ?User $booster,
        string $serviceType,
        string $status = OrderStatus::IN_PROGRESS,
        array $overrides = []
    ): Order {
        $pricingInput = $this->pricingInputFor($serviceType);
        $pricedPayload = app(ValorantPricingEngine::class)->calculateOrFail(
            $pricingInput,
            ['allowExtendedRankedWins' => ($pricingInput['serviceType'] ?? null) === 'Ranked Wins']
        );
        $priceCents = (int) round(((float) data_get($pricedPayload, 'pricing.total', 0)) * 100);
        $details = array_merge([
            'game' => 'VALORANT',
            'service' => $serviceType,
            'from' => data_get($pricedPayload, 'currentDivision', 'Unranked'),
            'to' => data_get($pricedPayload, 'desiredDivision', 'Unranked'),
            'currentRR' => data_get($pricedPayload, 'currentRR'),
            'averageRR' => data_get($pricedPayload, 'averageRR'),
            'region' => data_get($pricedPayload, 'region', 'EU'),
            'platform' => data_get($pricedPayload, 'platform', 'PC'),
            'accountType' => data_get($pricedPayload, 'accountType', 'Account Shared'),
            'addons' => data_get($pricedPayload, 'addons', []),
            'order' => $pricedPayload,
        ], $overrides['details'] ?? []);

        return Order::query()->create(array_merge([
            'user_id' => $customer->id,
            'booster_id' => $booster?->id,
            'order_number' => (string) Str::orderedUuid(),
            'product' => $serviceType,
            'status' => $status,
            'payment_status' => 'paid',
            'price_cents' => $priceCents,
            'booster_payout_rate' => Order::configuredBoosterPayoutPercentage(),
            'booster_payout_cents' => (int) round($priceCents * Order::configuredBoosterPayoutRate()),
            'currency' => 'USD',
            'details' => $details,
            'metadata' => [
                'customer' => [
                    'email' => $customer->email,
                ],
                'contactMethod' => 'discord',
            ],
            'contact_method' => 'discord',
            'discord' => 'customer#1234',
            'assigned_at' => $booster ? now() : null,
            'paid_at' => now(),
        ], $overrides));
    }

    protected function pricingInputFor(string $serviceType): array
    {
        return match ($serviceType) {
            'Ranked Wins' => [
                'serviceType' => 'Ranked Wins',
                'currentDivision' => 'Gold I',
                'region' => 'EU',
                'platform' => 'PC',
                'boostMode' => 'normal',
                'numberOfWins' => 10,
                'selectedAddons' => ['Offline Mode'],
            ],
            'Placement Matches' => [
                'serviceType' => 'Placement Matches',
                'currentDivision' => 'Silver I',
                'region' => 'NA',
                'platform' => 'PC',
                'boostMode' => 'normal',
                'numberOfPlacementGames' => 5,
                'selectedAddons' => ['Offline Mode'],
            ],
            'Radiant Boost' => [
                'serviceType' => 'Radiant Boost',
                'currentDivision' => 'Immortal I',
                'desiredDivision' => 'Radiant',
                'avgRRPerWin' => '18 OR LOWER',
                'region' => 'EU',
                'platform' => 'PC',
                'boostMode' => 'normal',
                'selectedAddons' => ['Offline Mode'],
            ],
            default => [
                'serviceType' => 'Rank Boosting',
                'currentDivision' => 'Iron III',
                'desiredDivision' => 'Silver III',
                'currentRR' => 0,
                'avgRRPerWin' => '18 OR LOWER',
                'region' => 'EU',
                'platform' => 'PC',
                'boostMode' => 'normal',
                'selectedAddons' => ['Offline Mode'],
            ],
        };
    }
}
