<?php

namespace Tests\Feature;

use App\Enums\OrderChatThreadType;
use App\Models\Order;
use App\Models\OrderChatMessage;
use App\Models\User;
use App\Services\Chat\EnsureOrderChatThreads;
use App\Support\OrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ChatRouteCanonicalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_chat_route_generation_uses_canonical_plural_urls_and_visible_order_number(): void
    {
        $customer = $this->makeUser('customer');
        $booster = $this->makeUser('booster');
        $order = $this->makeOrder($customer, $booster);

        $this->assertSame(url('/user/chats/'.$order->order_number), route('user-chats.show', ['order' => $order]));
        $this->assertSame(url('/booster/chats/'.$order->order_number), route('booster-chats.show', ['order' => $order]));
        $this->assertSame(url('/admin/chats/'.$order->order_number), route('admin-chats.show', ['order' => $order]));
        $this->assertSame(url('/booster/chats/all'), route('booster-chats'));
        $this->assertSame(
            url('/orders/'.$order->order_number.'/chats/customer_admin/messages'),
            route('order-chat.messages.index', ['order' => $order, 'threadType' => OrderChatThreadType::CUSTOMER_ADMIN->value])
        );
        $this->assertStringNotContainsString('/boost/chats', route('booster-chats.show', ['order' => $order]));
        $this->assertStringNotContainsString('/chat/', route('user-chats.show', ['order' => $order]));
    }

    public function test_user_chat_page_resolves_at_canonical_order_number_url_and_redirects_legacy_variants(): void
    {
        $customer = $this->makeUser('customer');
        $booster = $this->makeUser('booster');
        $order = $this->makeOrder($customer, $booster);

        $canonicalUrl = route('user-chats.show', ['order' => $order]);

        $this->actingAs($customer)
            ->get($canonicalUrl)
            ->assertOk();

        $this->actingAs($customer)
            ->get('/user/chat/'.$order->order_number)
            ->assertRedirect($canonicalUrl);

        $this->actingAs($customer)
            ->get('/user/chats/'.$order->id)
            ->assertRedirect($canonicalUrl);
    }

    public function test_booster_chat_page_resolves_at_canonical_order_number_url_and_redirects_legacy_variants(): void
    {
        $customer = $this->makeUser('customer');
        $booster = $this->makeUser('booster');
        $order = $this->makeOrder($customer, $booster);

        $canonicalUrl = route('booster-chats.show', ['order' => $order]);

        $this->actingAs($booster)
            ->get($canonicalUrl)
            ->assertOk();

        $this->actingAs($booster)
            ->get('/booster/chat/'.$order->order_number)
            ->assertRedirect($canonicalUrl);

        $this->actingAs($booster)
            ->get('/boost/chats/'.$order->id)
            ->assertRedirect($canonicalUrl);

        $this->actingAs($booster)
            ->get('/boost/chat/'.$order->order_number)
            ->assertRedirect($canonicalUrl);

        $this->actingAs($booster)
            ->get('/booster/chats')
            ->assertRedirect(route('booster-chats'));
    }

    public function test_admin_chat_page_resolves_at_canonical_order_number_url_and_redirects_legacy_variants(): void
    {
        $admin = $this->makeUser('admin');
        $customer = $this->makeUser('customer');
        $booster = $this->makeUser('booster');
        $order = $this->makeOrder($customer, $booster);

        $canonicalUrl = route('admin-chats.show', ['order' => $order]);

        $this->actingAs($admin)
            ->get($canonicalUrl)
            ->assertOk();

        $this->actingAs($admin)
            ->get('/admin/chat/'.$order->order_number)
            ->assertRedirect($canonicalUrl);

        $this->actingAs($admin)
            ->get('/admin/chats/'.$order->id)
            ->assertRedirect($canonicalUrl);
    }

    public function test_invalid_chat_order_identifier_returns_not_found(): void
    {
        $customer = $this->makeUser('customer');
        $booster = $this->makeUser('booster');
        $admin = $this->makeUser('admin');

        $this->actingAs($customer)
            ->get('/user/chats/not-a-real-order')
            ->assertNotFound();

        $this->actingAs($booster)
            ->get('/booster/chats/not-a-real-order')
            ->assertNotFound();

        $this->actingAs($admin)
            ->get('/admin/chats/not-a-real-order')
            ->assertNotFound();
    }

    public function test_chat_page_authorization_remains_protected_on_canonical_urls(): void
    {
        $customer = $this->makeUser('customer');
        $otherCustomer = $this->makeUser('customer');
        $booster = $this->makeUser('booster');
        $otherBooster = $this->makeUser('booster');
        $admin = $this->makeUser('admin');
        $order = $this->makeOrder($customer, $booster);

        $this->actingAs($otherCustomer)
            ->get(route('user-chats.show', ['order' => $order]))
            ->assertForbidden();

        $this->actingAs($otherBooster)
            ->get(route('booster-chats.show', ['order' => $order]))
            ->assertForbidden();

        $this->actingAs($admin)
            ->get(route('admin-chats.show', ['order' => $order]))
            ->assertOk();
    }

    public function test_rendered_open_chat_links_use_canonical_urls(): void
    {
        $customer = $this->makeUser('customer');
        $booster = $this->makeUser('booster');
        $admin = $this->makeUser('admin');
        $order = $this->makeOrder($customer, $booster);

        $this->createChatMessage($order, OrderChatThreadType::CUSTOMER_ADMIN, $customer, 'Need an update.');

        $this->actingAs($customer)
            ->get(route('allorders'))
            ->assertOk()
            ->assertSee(route('user-chats.show', ['order' => $order]), false)
            ->assertSee('encodeURIComponent(order.orderNumber || \'\')', false)
            ->assertDontSee('/user/chats/'.$order->id, false);

        $this->actingAs($booster)
            ->get(route('booster-orders'))
            ->assertOk()
            ->assertSee(route('booster-chats.show', ['order' => $order]), false)
            ->assertDontSee('/boost/chats/'.$order->order_number, false);

        $this->actingAs($admin)
            ->get(route('admin-chats'))
            ->assertOk()
            ->assertSee(route('admin-chats.show', ['order' => $order]), false)
            ->assertDontSee('/admin/chats/'.$order->id, false);
    }

    protected function makeUser(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'account_status' => 'active',
        ]);
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
}
