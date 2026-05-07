<?php

namespace Tests\Feature;

use App\Enums\OrderChatThreadType;
use App\Events\OrderChatMessageSent;
use App\Models\Order;
use App\Models\OrderChatMessage;
use App\Models\User;
use App\Services\Chat\EnsureOrderChatThreads;
use App\Services\Chat\OrderChatAuthorizationService;
use App\Support\OrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderChatFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_only_access_their_allowed_threads(): void
    {
        $customer = $this->makeUser('customer');
        $booster = $this->makeUser('booster');
        $otherCustomer = $this->makeUser('customer');
        $order = $this->makeOrder($customer, $booster);

        $this->actingAs($customer)
            ->get(route('order-chat.messages.index', ['order' => $order, 'threadType' => OrderChatThreadType::CUSTOMER_BOOSTER->value]))
            ->assertOk();

        $this->actingAs($customer)
            ->get(route('order-chat.messages.index', ['order' => $order, 'threadType' => OrderChatThreadType::CUSTOMER_ADMIN->value]))
            ->assertOk();

        $this->actingAs($customer)
            ->get(route('order-chat.messages.index', ['order' => $order, 'threadType' => OrderChatThreadType::BOOSTER_ADMIN->value]))
            ->assertForbidden();

        $this->actingAs($otherCustomer)
            ->get(route('order-chat.messages.index', ['order' => $order, 'threadType' => OrderChatThreadType::CUSTOMER_BOOSTER->value]))
            ->assertForbidden();
    }

    public function test_booster_can_only_access_assigned_allowed_threads(): void
    {
        $customer = $this->makeUser('customer');
        $booster = $this->makeUser('booster');
        $otherBooster = $this->makeUser('booster');
        $order = $this->makeOrder($customer, $booster);

        $this->actingAs($booster)
            ->get(route('order-chat.messages.index', ['order' => $order, 'threadType' => OrderChatThreadType::CUSTOMER_BOOSTER->value]))
            ->assertOk();

        $this->actingAs($booster)
            ->get(route('order-chat.messages.index', ['order' => $order, 'threadType' => OrderChatThreadType::BOOSTER_ADMIN->value]))
            ->assertOk();

        $this->actingAs($booster)
            ->get(route('order-chat.messages.index', ['order' => $order, 'threadType' => OrderChatThreadType::CUSTOMER_ADMIN->value]))
            ->assertForbidden();

        $this->actingAs($otherBooster)
            ->get(route('order-chat.messages.index', ['order' => $order, 'threadType' => OrderChatThreadType::CUSTOMER_BOOSTER->value]))
            ->assertForbidden();
    }

    public function test_admin_can_view_all_threads_but_cannot_send_into_customer_booster_thread(): void
    {
        $admin = $this->makeUser('admin');
        $customer = $this->makeUser('customer');
        $booster = $this->makeUser('booster');
        $order = $this->makeOrder($customer, $booster);

        foreach (OrderChatThreadType::cases() as $threadType) {
            $this->actingAs($admin)
                ->get(route('order-chat.messages.index', ['order' => $order, 'threadType' => $threadType->value]))
                ->assertOk();
        }

        $this->actingAs($admin)
            ->post(route('order-chat.messages.store', ['order' => $order, 'threadType' => OrderChatThreadType::CUSTOMER_BOOSTER->value]), [
                'body' => 'Admin should not post here.',
            ])
            ->assertForbidden();

        $this->actingAs($admin)
            ->post(route('order-chat.messages.store', ['order' => $order, 'threadType' => OrderChatThreadType::BOOSTER_ADMIN->value]), [
                'body' => 'Internal admin note.',
            ])
            ->assertCreated();
    }

    public function test_messages_never_leak_across_threads(): void
    {
        $customer = $this->makeUser('customer');
        $booster = $this->makeUser('booster');
        $admin = $this->makeUser('admin');
        $order = $this->makeOrder($customer, $booster);

        $this->createChatMessage($order, OrderChatThreadType::CUSTOMER_ADMIN, $customer, 'Customer to admin');
        $this->createChatMessage($order, OrderChatThreadType::BOOSTER_ADMIN, $booster, 'Booster to admin');

        $response = $this->actingAs($admin)
            ->get(route('order-chat.messages.index', ['order' => $order, 'threadType' => OrderChatThreadType::CUSTOMER_ADMIN->value]));

        $response->assertOk();
        $response->assertJsonCount(1, 'messages');
        $this->assertSame('Customer to admin', $response->json('messages.0.body'));
        $this->assertSame(OrderChatThreadType::CUSTOMER_ADMIN->value, $response->json('messages.0.thread_type'));
    }

    public function test_message_validation_rejects_empty_oversized_and_invalid_thread_types(): void
    {
        $customer = $this->makeUser('customer');
        $booster = $this->makeUser('booster');
        $order = $this->makeOrder($customer, $booster);

        $this->actingAs($customer)
            ->post(route('order-chat.messages.store', ['order' => $order, 'threadType' => OrderChatThreadType::CUSTOMER_ADMIN->value]), [
                'body' => '   ',
            ])
            ->assertSessionHasErrors('body');

        $this->actingAs($customer)
            ->post(route('order-chat.messages.store', ['order' => $order, 'threadType' => OrderChatThreadType::CUSTOMER_ADMIN->value]), [
                'body' => str_repeat('A', 3001),
            ])
            ->assertSessionHasErrors('body');

        $this->actingAs($customer)
            ->get(route('order-chat.messages.index', ['order' => $order, 'threadType' => 'not-a-thread']))
            ->assertNotFound();
    }

    public function test_authorized_send_persists_message_and_dispatches_event(): void
    {
        Event::fake([OrderChatMessageSent::class]);

        $customer = $this->makeUser('customer');
        $booster = $this->makeUser('booster');
        $order = $this->makeOrder($customer, $booster);

        $response = $this->actingAs($customer)
            ->post(route('order-chat.messages.store', ['order' => $order, 'threadType' => OrderChatThreadType::CUSTOMER_ADMIN->value]), [
                'body' => 'Need help with my order.',
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('order_chat_messages', [
            'sender_id' => $customer->id,
            'sender_role' => 'customer',
            'sender_name' => $customer->name,
            'body' => 'Need help with my order.',
        ]);

        Event::assertDispatched(OrderChatMessageSent::class, function (OrderChatMessageSent $event) use ($order, $customer): bool {
            return (int) $event->message->sender_id === (int) $customer->id
                && (int) $event->message->thread->order_id === (int) $order->id
                && $event->message->thread->thread_type === OrderChatThreadType::CUSTOMER_ADMIN;
        });
    }

    public function test_history_loading_is_paginated(): void
    {
        $customer = $this->makeUser('customer');
        $booster = $this->makeUser('booster');
        $order = $this->makeOrder($customer, $booster);

        for ($index = 1; $index <= 30; $index++) {
            $this->createChatMessage($order, OrderChatThreadType::CUSTOMER_BOOSTER, $customer, "Message {$index}");
        }

        $firstPage = $this->actingAs($customer)
            ->get(route('order-chat.messages.index', ['order' => $order, 'threadType' => OrderChatThreadType::CUSTOMER_BOOSTER->value]));

        $firstPage->assertOk();
        $firstPage->assertJsonCount(25, 'messages');
        $this->assertTrue($firstPage->json('meta.has_more'));
        $this->assertNotNull($firstPage->json('meta.next_cursor'));

        $olderPage = $this->actingAs($customer)
            ->get(route('order-chat.messages.index', [
                'order' => $order,
                'threadType' => OrderChatThreadType::CUSTOMER_BOOSTER->value,
                'before' => $firstPage->json('meta.next_cursor'),
            ]));

        $olderPage->assertOk();
        $olderPage->assertJsonCount(5, 'messages');
    }

    public function test_broadcast_channel_authorization_is_thread_scoped(): void
    {
        config(['broadcasting.default' => 'pusher']);

        $customer = $this->makeUser('customer');
        $booster = $this->makeUser('booster');
        $admin = $this->makeUser('admin');
        $order = $this->makeOrder($customer, $booster);
        $service = app(OrderChatAuthorizationService::class);

        $this->assertTrue($service->canSubscribe($customer, $order->id, OrderChatThreadType::CUSTOMER_ADMIN->value));
        $this->assertFalse($service->canSubscribe($customer, $order->id, OrderChatThreadType::BOOSTER_ADMIN->value));
        $this->assertTrue($service->canSubscribe($admin, $order->id, OrderChatThreadType::CUSTOMER_BOOSTER->value));

        $this->actingAs($customer)
            ->post('/broadcasting/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => "private-order-chat.{$order->id}.".OrderChatThreadType::BOOSTER_ADMIN->value,
            ])
            ->assertForbidden();
    }

    public function test_existing_chat_pages_render_realtime_hooks(): void
    {
        $customer = $this->makeUser('customer');
        $booster = $this->makeUser('booster');
        $admin = $this->makeUser('admin');
        $order = $this->makeOrder($customer, $booster);

        $adminResponse = $this->actingAs($admin)
            ->get(route('admin-chats.show', $order));

        $adminResponse
            ->assertOk()
            ->assertSee('data-order-chat-app', false)
            ->assertSee("order-chat.{$order->id}.customer_booster", false)
            ->assertSee('data-chat-can-send="0"', false)
            ->assertSee('adminControlsModal', false)
            ->assertSee('adminOrderDetailsModal', false)
            ->assertSee('adminProgressTrackerModal', false)
            ->assertSee('Customer')
            ->assertSee('Booster')
            ->assertSee('Order Chat')
            ->assertSee('ggwp-chat-shell--user', false)
            ->assertSee('chat-main-card--user', false)
            ->assertSee('data-rank-fallback-src=', false)
            ->assertDontSee('Admin command view')
            ->assertSee('assets/chats/send_button.png', false);

        $this->actingAs($booster)
            ->get(route('booster-chats.show', $order))
            ->assertOk()
            ->assertSee('data-chat-history-url=', false)
            ->assertSee("order-chat.{$order->id}.booster_admin", false);

        $this->actingAs($customer)
            ->get(route('user-chats.show', $order))
            ->assertOk()
            ->assertSee('data-chat-send-url=', false)
            ->assertSee("order-chat.{$order->id}.customer_admin", false)
            ->assertSee('assets/chats/send_button.png', false)
            ->assertDontSee('Your order workspace');
    }

    public function test_chat_tabs_use_role_relative_labels_and_expected_order(): void
    {
        $customer = $this->makeUser('customer');
        $booster = $this->makeUser('booster');
        $admin = $this->makeUser('admin');
        $order = $this->makeOrder($customer, $booster);

        $adminHtml = $this->actingAs($admin)->get(route('admin-chats.show', $order))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/data-chat-channel-label="Booster".*data-chat-channel-label="Customer".*data-chat-channel-label="Order Chat"/s', $adminHtml);

        $boosterHtml = $this->actingAs($booster)->get(route('booster-chats.show', $order))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/data-chat-channel-label="Customer".*data-chat-channel-label="Admin"/s', $boosterHtml);
        $this->assertStringNotContainsString('data-chat-channel-label="Order Chat"', $boosterHtml);

        $customerHtml = $this->actingAs($customer)->get(route('user-chats.show', $order))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/data-chat-channel-label="Booster".*data-chat-channel-label="Admin"/s', $customerHtml);
        $this->assertStringNotContainsString('data-chat-channel-label="Order Chat"', $customerHtml);
    }

    public function test_booster_chat_page_renders_only_the_required_workspace_cards(): void
    {
        $customer = $this->makeUser('customer');
        $booster = $this->makeUser('booster');
        $order = $this->makeOrder($customer, $booster);

        $this->actingAs($booster)
            ->get(route('booster-chats.show', $order))
            ->assertOk()
            ->assertSee('Rank tracker')
            ->assertSee('Order Details')
            ->assertSee('Customer Brief')
            ->assertSee('Booster controls')
            ->assertSee('Current Rank')
            ->assertSee('Mark as Completed')
            ->assertSee('Drop Order')
            ->assertSee('ChatBox')
            ->assertSee('boosterDropConfirmModal', false)
            ->assertSee('boosterCompleteProofModal', false)
            ->assertSee('boosterCompleteCaptchaModal', false)
            ->assertSee('assets/chats/send_button.png', false)
            ->assertSee('Start Rank')
            ->assertSee('Desired Rank')
            ->assertSee('Addons')
            ->assertSee('Payout')
            ->assertDontSee('Order Snapshot')
            ->assertDontSee('Customer preferences')
            ->assertSee('Average RR Gain')
            ->assertSee('Update by')
            ->assertSee('Update at')
            ->assertDontSee('Preferred Contact')
            ->assertDontSee('Contact Handle')
            ->assertDontSee('Pause Order')
            ->assertDontSee('Payout Basis');
    }

    public function test_admin_rank_tracker_fields_follow_role_and_service_rules(): void
    {
        $admin = $this->makeUser('admin');
        $customer = $this->makeUser('customer');
        $booster = $this->makeUser('booster');

        foreach ($this->rankTrackerMatrix('admin') as $serviceType => $expectations) {
            $order = $this->makeTrackedOrder($customer, $booster, $serviceType);

            $response = $this->actingAs($admin)
                ->get(route('admin-chats.show', $order))
                ->assertOk();

            $this->assertRankTrackerFields($response, $expectations);
        }
    }

    public function test_customer_rank_tracker_fields_follow_role_and_service_rules_without_changing_actions(): void
    {
        $customer = $this->makeUser('customer');
        $booster = $this->makeUser('booster');

        foreach ($this->rankTrackerMatrix('customer') as $serviceType => $expectations) {
            $order = $this->makeTrackedOrder($customer, $booster, $serviceType);

            $response = $this->actingAs($customer)
                ->get(route('user-chats.show', $order))
                ->assertOk()
                ->assertSee('Actions');

            $this->assertRankTrackerFields($response, $expectations);
        }
    }

    public function test_booster_rank_tracker_fields_follow_role_and_service_rules_without_changing_controls(): void
    {
        $customer = $this->makeUser('customer');
        $booster = $this->makeUser('booster');

        foreach ($this->rankTrackerMatrix('booster') as $serviceType => $expectations) {
            $order = $this->makeTrackedOrder($customer, $booster, $serviceType);

            $response = $this->actingAs($booster)
                ->get(route('booster-chats.show', $order))
                ->assertOk()
                ->assertSee('Booster controls');

            $this->assertRankTrackerFields($response, $expectations);
        }
    }

    public function test_admin_chat_index_renders_the_chat_list(): void
    {
        $admin = $this->makeUser('admin');
        $customer = $this->makeUser('customer');
        $booster = $this->makeUser('booster');
        $order = $this->makeOrder($customer, $booster);
        $silentOrder = $this->makeOrder($customer, $booster);

        $this->createChatMessage($order, OrderChatThreadType::CUSTOMER_ADMIN, $customer, 'Need an update on this order.');

        $this->actingAs($admin)
            ->get(route('admin-chats'))
            ->assertOk()
            ->assertSee('Active Chat Orders')
            ->assertSee($order->order_number)
            ->assertSee('Customer / Admin')
            ->assertSee('Open Chat')
            ->assertDontSee($silentOrder->order_number)
            ->assertDontSee('Latest message');
    }

    public function test_chat_empty_states_use_the_correct_participant_labels(): void
    {
        $customer = $this->makeUser('customer');
        $booster = $this->makeUser('booster');
        $admin = $this->makeUser('admin');
        $order = $this->makeOrder($customer, $booster);

        $this->actingAs($customer)
            ->get(route('user-chats.show', $order))
            ->assertOk()
            ->assertSee('No Conversation between You and Admin yet.')
            ->assertSee('No Conversation between You and Booster yet.');

        $this->actingAs($booster)
            ->get(route('booster-chats.show', $order))
            ->assertOk()
            ->assertSee('No Conversation between You and Admin yet.')
            ->assertSee('No Conversation between You and Customer yet.');

        $this->actingAs($admin)
            ->get(route('admin-chats.show', $order))
            ->assertOk()
            ->assertSee('No Conversation between Admin and Customer yet.')
            ->assertSee('No Conversation between Admin and Booster yet.')
            ->assertSee('No Conversation between Customer and Booster yet.');
    }

    public function test_html_payloads_round_trip_as_plain_text_payloads(): void
    {
        $customer = $this->makeUser('customer');
        $booster = $this->makeUser('booster');
        $order = $this->makeOrder($customer, $booster);
        $payload = '<script>alert("xss")</script>';

        $response = $this->actingAs($customer)
            ->post(route('order-chat.messages.store', ['order' => $order, 'threadType' => OrderChatThreadType::CUSTOMER_ADMIN->value]), [
                'body' => $payload,
            ]);

        $response->assertCreated();
        $this->assertSame($payload, $response->json('message.body'));

        $history = $this->actingAs($customer)
            ->get(route('order-chat.messages.index', ['order' => $order, 'threadType' => OrderChatThreadType::CUSTOMER_ADMIN->value]));

        $history->assertOk();
        $this->assertSame($payload, $history->json('messages.0.body'));
    }

    public function test_chat_pages_render_agent_selection_view_triggers_when_the_order_has_saved_agents(): void
    {
        $customer = $this->makeUser('customer');
        $booster = $this->makeUser('booster');
        $admin = $this->makeUser('admin');
        $order = $this->makeOrder($customer, $booster, OrderStatus::IN_PROGRESS);
        $specificAgents = $this->agentUuids(3);
        $oneTrickAgent = $this->agentUuids(1);

        $order->forceFill([
            'details' => [
                'addons' => ['Specific Agents', 'One-Trick Agent', 'Offline Mode'],
                'order' => [
                    'orderType' => 'Rank Boosting',
                    'currentDivision' => 'Gold I',
                    'desiredDivision' => 'Platinum I',
                    'addons' => ['Specific Agents', 'One-Trick Agent', 'Offline Mode'],
                    'specificAgents' => $specificAgents,
                    'oneTrickAgent' => $oneTrickAgent,
                ],
            ],
        ])->save();

        $this->actingAs($customer)
            ->get(route('user-chats.show', $order))
            ->assertOk()
            ->assertSee('data-agent-selector-view-trigger', false)
            ->assertSee('See Specific Agents')
            ->assertSee('See One-Trick Agent');

        $this->actingAs($booster)
            ->get(route('booster-chats.show', $order))
            ->assertOk()
            ->assertSee('data-agent-selector-view-trigger', false)
            ->assertSee('See Specific Agents')
            ->assertSee('See One-Trick Agent');

        $this->actingAs($admin)
            ->get(route('admin-chats.show', $order))
            ->assertOk()
            ->assertSee('data-agent-selector-view-trigger', false)
            ->assertSee('See Specific Agents')
            ->assertSee('See One-Trick Agent');
    }

    protected function makeUser(string $role, array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'role' => $role,
            'account_status' => 'active',
        ], $overrides));
    }

    protected function makeOrder(User $customer, ?User $booster = null, string $status = OrderStatus::IN_PROGRESS): Order
    {
        return Order::query()->create([
            'user_id' => $customer->id,
            'booster_id' => $booster?->id,
            'order_number' => (string) Str::orderedUuid(),
            'product' => 'Rank Boosting',
            'status' => $status,
            'payment_status' => 'paid',
            'price_cents' => 15000,
            'booster_payout_rate' => 0.6,
            'booster_payout_cents' => 9000,
            'currency' => 'USD',
            'details' => [
                'order' => [
                    'orderType' => 'Rank Boosting',
                    'currentDivision' => 'Gold I',
                    'desiredDivision' => 'Platinum I',
                ],
            ],
            'metadata' => [
                'customer' => [
                    'email' => $customer->email,
                ],
            ],
            'contact_method' => 'discord',
            'whatsapp' => '+15555555555',
            'discord' => 'customer#1234',
            'assigned_at' => $booster ? now() : null,
        ]);
    }

    protected function createChatMessage(Order $order, OrderChatThreadType $threadType, User $sender, string $body): OrderChatMessage
    {
        $thread = app(EnsureOrderChatThreads::class)->thread($order, $threadType);

        return $thread->messages()->create([
            'sender_id' => $sender->id,
            'sender_role' => (string) $sender->role,
            'sender_name' => $sender->name,
            'body' => $body,
        ]);
    }

    protected function makeTrackedOrder(User $customer, ?User $booster, string $serviceType): Order
    {
        [$orderPayload, $progressPayload, $priceCents] = match ($serviceType) {
            'Ranked Wins' => [[
                'orderType' => 'Ranked Wins',
                'currentDivision' => 'Gold I',
                'desiredDivision' => '10 Wins',
                'numberOfWins' => 10,
            ], [
                'currentRank' => 'Gold III',
                'completedWins' => 4,
                'pct' => 40,
                'updatedBy' => 'Booster Prime',
                'updatedAt' => now()->subMinutes(30)->toIso8601String(),
            ], 11000],
            'Placement Matches' => [[
                'orderType' => 'Placement Matches',
                'currentDivision' => 'Silver I',
                'numberOfPlacementGames' => 5,
            ], [
                'completedPlacements' => 3,
                'pct' => 60,
                'updatedBy' => 'Booster Prime',
                'updatedAt' => now()->subMinutes(20)->toIso8601String(),
            ], 9000],
            'Radiant Boost' => [[
                'orderType' => 'Radiant Boost',
                'currentDivision' => 'Immortal I',
                'desiredDivision' => 'Radiant',
                'averageRR' => '18 OR LOWER',
            ], [
                'currentRank' => 'Immortal II',
                'currentRR' => 50,
                'pct' => 50,
                'updatedBy' => 'Booster Prime',
                'updatedAt' => now()->subMinutes(10)->toIso8601String(),
            ], 24000],
            default => [[
                'orderType' => 'Rank Boosting',
                'currentDivision' => 'Iron III',
                'desiredDivision' => 'Silver III',
                'currentRR' => 0,
                'averageRR' => '18 OR LOWER',
            ], [
                'currentRank' => 'Bronze III',
                'currentRR' => 20,
                'pct' => 50,
                'updatedBy' => 'Booster Prime',
                'updatedAt' => now()->subMinutes(15)->toIso8601String(),
            ], 15000],
        };

        return Order::query()->create([
            'user_id' => $customer->id,
            'booster_id' => $booster?->id,
            'order_number' => (string) Str::orderedUuid(),
            'product' => $serviceType,
            'status' => OrderStatus::IN_PROGRESS,
            'payment_status' => 'paid',
            'price_cents' => $priceCents,
            'booster_payout_rate' => 0.6,
            'booster_payout_cents' => (int) round($priceCents * 0.6),
            'currency' => 'USD',
            'details' => [
                'service' => $serviceType,
                'game' => 'VALORANT',
                'from' => data_get($orderPayload, 'currentDivision', 'Unranked'),
                'to' => data_get($orderPayload, 'desiredDivision', 'Unranked'),
                'averageRR' => data_get($orderPayload, 'averageRR', '18 OR LOWER'),
                'order' => $orderPayload,
                'progress' => $progressPayload,
            ],
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
        ]);
    }

    protected function rankTrackerMatrix(string $role): array
    {
        $shared = [
            'Ranked Wins' => [
                'present' => [
                    'data-order-bind="fromRank"',
                    'data-order-bind="currentRank"',
                    'data-order-bind="winsDone"',
                    'data-order-bind="progressPct"',
                ],
                'absent' => [
                    'data-order-bind="desiredRank"',
                    'data-order-bind="placementsPlayed"',
                    'data-order-bind="averageRR"',
                    'data-order-bind="progressUpdatedBy"',
                    'data-order-bind="progressUpdatedAt"',
                ],
            ],
            'Placement Matches' => [
                'present' => [
                    'data-order-bind="placementsPlayed"',
                    'data-order-bind="progressPct"',
                ],
                'absent' => [
                    'data-order-bind="fromRank"',
                    'data-order-bind="currentRank"',
                    'data-order-bind="desiredRank"',
                    'data-order-bind="winsDone"',
                    'data-order-bind="averageRR"',
                    'data-order-bind="progressUpdatedBy"',
                    'data-order-bind="progressUpdatedAt"',
                ],
            ],
        ];

        $rankJourney = [
            'present' => [
                'data-order-bind="fromRank"',
                'data-order-bind="currentRank"',
                'data-order-bind="desiredRank"',
                'data-order-bind="progressPct"',
            ],
            'absent' => [
                'data-order-bind="winsDone"',
                'data-order-bind="placementsPlayed"',
            ],
        ];

        $advanced = $role === 'customer'
            ? [
                'data-order-bind="averageRR"',
                'data-order-bind="progressUpdatedBy"',
                'data-order-bind="progressUpdatedAt"',
            ]
            : [];

        $rankBoostRules = [
            'present' => array_merge($rankJourney['present'], $role === 'customer' ? [] : [
                'data-order-bind="averageRR"',
                'data-order-bind="progressUpdatedBy"',
                'data-order-bind="progressUpdatedAt"',
            ]),
            'absent' => array_merge($rankJourney['absent'], $advanced),
        ];

        return [
            'Rank Boosting' => $rankBoostRules,
            'Ranked Wins' => $shared['Ranked Wins'],
            'Placement Matches' => $shared['Placement Matches'],
            'Radiant Boost' => $rankBoostRules,
        ];
    }

    protected function assertRankTrackerFields(TestResponse $response, array $expectations): void
    {
        foreach ($expectations['present'] as $needle) {
            $response->assertSee($needle, false);
        }

        foreach ($expectations['absent'] as $needle) {
            $response->assertDontSee($needle, false);
        }
    }

    protected function agentUuids(int $count): array
    {
        return collect(config('valorant_agents', []))
            ->pluck('uuid')
            ->filter()
            ->take($count)
            ->values()
            ->all();
    }
}
