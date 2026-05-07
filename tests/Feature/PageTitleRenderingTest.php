<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Promotion;
use App\Models\PromoCode;
use App\Models\User;
use App\Support\OrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PageTitleRenderingTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('publicPageProvider')]
    public function test_public_pages_render_one_exact_seo_title(string $routeName, string $expectedTitle): void
    {
        $response = $this->get(route($routeName));

        $this->assertPageTitle($response, $expectedTitle);
    }

    public static function publicPageProvider(): array
    {
        return [
            'home' => ['home', 'VALORANT Rank Boosting | Fast, Safe VALORANT Boost | GGWP-Boost'],
            'blog' => ['blog.index', 'VALORANT Boosting Blog | Rank Boosting Guides | GGWP-Boost'],
            'contact' => ['contact', 'VALORANT Boosting Support & Contact | GGWP-Boost'],
            'faq' => ['faq', 'VALORANT Boosting FAQ | Safety, Speed & Pricing | GGWP-Boost'],
            'checkout' => ['checkout', 'VALORANT Boost Pricing | Cheap & Fast Rank Boosting For VALORANT | GGWP-Boost'],
            'code of ethics' => ['code-of-ethics', 'Code of Ethics | GGWP-Boost'],
            'privacy policy' => ['privacy-policy', 'Privacy Policy | GGWP-Boost'],
            'refund policy' => ['refund-policy', 'Refund Policy | GGWP-Boost'],
            'reviews' => ['reviews', 'VALORANT Boosting Reviews | Customer Proof | GGWP-Boost'],
            'terms and conditions' => ['terms-and-conditions', 'Terms and Conditions | GGWP-Boost'],
            'login' => ['login', 'Login | GGWP-Boost'],
            'signup' => ['signup', 'Create Account | GGWP-Boost'],
            'become booster' => ['become-booster', 'Become a VALORANT Booster | Apply Today | GGWP-Boost'],
        ];
    }

    public function test_customer_workspace_pages_use_stable_route_specific_titles(): void
    {
        $customer = $this->makeUser('customer');
        $booster = $this->makeUser('booster');
        $order = $this->makeOrder($customer, $booster);

        $this->assertPageTitle(
            $this->actingAs($customer)->get(route('customer-dashboard')),
            'Customer Dashboard | GGWP-Boost'
        );

        $this->assertPageTitle(
            $this->actingAs($customer)->get(route('allorders')),
            'Order History | GGWP-Boost'
        );

        $this->assertPageTitle(
            $this->actingAs($customer)->get(route('customer-upgrade-order')),
            'Extend / Upgrade Boost | GGWP-Boost'
        );

        $this->assertPageTitle(
            $this->actingAs($customer)->get(route('user-chats.show', ['order' => $order])),
            sprintf('Order #%s | GGWP-Boost', $order->order_number)
        );
    }

    public function test_customer_chat_index_without_orders_keeps_the_placeholder_title_stable(): void
    {
        $customer = $this->makeUser('customer');

        $this->assertPageTitle(
            $this->actingAs($customer)->get(route('user-chats')),
            'Order Workspace | GGWP-Boost'
        );
    }

    public function test_booster_workspace_pages_use_stable_route_specific_titles(): void
    {
        $customer = $this->makeUser('customer');
        $booster = $this->makeUser('booster');
        $order = $this->makeOrder($customer, $booster);

        $this->assertPageTitle(
            $this->actingAs($booster)->get(route('booster-dashboard')),
            'Booster Dashboard | GGWP-Boost'
        );

        $this->assertPageTitle(
            $this->actingAs($booster)->get(route('booster-orders')),
            'Booster Orders | GGWP-Boost'
        );

        $this->assertPageTitle(
            $this->actingAs($booster)->get(route('booster-wallet')),
            'Booster Wallet | GGWP-Boost'
        );

        $this->assertPageTitle(
            $this->actingAs($booster)->get(route('booster-claim-orders')),
            'Claim Orders | GGWP-Boost'
        );

        $this->assertPageTitle(
            $this->actingAs($booster)->get(route('booster-chats.show', ['order' => $order])),
            sprintf('Booster Order #%s | GGWP-Boost', $order->order_number)
        );
    }

    public function test_booster_chat_index_without_orders_keeps_the_placeholder_title_stable(): void
    {
        $booster = $this->makeUser('booster');

        $this->assertPageTitle(
            $this->actingAs($booster)->get(route('booster-chats')),
            'Booster Chats | GGWP-Boost'
        );
    }

    public function test_admin_pages_use_consistent_titles_for_indexes_and_detail_routes(): void
    {
        $admin = $this->makeUser('admin');
        $customer = $this->makeUser('customer', ['name' => 'Jordan Queue']);
        $booster = $this->makeUser('booster', ['name' => 'Ace Booster']);
        $order = $this->makeOrder($customer, $booster);
        $promoCode = PromoCode::factory()->create([
            'code' => 'BOOST10',
            'is_active' => true,
        ]);
        $promotion = Promotion::factory()->create([
            'title' => 'Weekend Rush',
        ]);

        $this->assertPageTitle(
            $this->actingAs($admin)->get(route('admin-dashboard')),
            'Admin Dashboard | GGWP-Boost'
        );

        $this->assertPageTitle(
            $this->actingAs($admin)->get(route('admin-chats')),
            'Admin Chats | GGWP-Boost'
        );

        $this->assertPageTitle(
            $this->actingAs($admin)->get(route('admin-chats.show', ['order' => $order])),
            sprintf('Admin Chat Order #%s | GGWP-Boost', $order->order_number)
        );

        $this->assertPageTitle(
            $this->actingAs($admin)->get(route('admin-promo-codes.index')),
            'Promo Codes | GGWP-Boost'
        );

        $this->assertPageTitle(
            $this->actingAs($admin)->get(route('admin-promotions.index')),
            'Promotions | GGWP-Boost'
        );

        $this->assertPageTitle(
            $this->actingAs($admin)->get(route('admin-promotions.edit', ['promotion' => $promotion])),
            'Edit Promotion Weekend Rush | GGWP-Boost'
        );

        $this->assertPageTitle(
            $this->actingAs($admin)->get(route('admin-promo-codes.details', ['promoCode' => $promoCode])),
            'Promo Code BOOST10 | GGWP-Boost'
        );

        $this->assertPageTitle(
            $this->actingAs($admin)->get(route('admin-promo-codes.edit', ['promoCode' => $promoCode])),
            'Edit Promo Code BOOST10 | GGWP-Boost'
        );

        $this->assertPageTitle(
            $this->actingAs($admin)->get(route('admin-customers.show', ['user' => $customer])),
            'Customer Profile '.$customer->publicIdentity('Customer').' | GGWP-Boost'
        );

        $this->assertPageTitle(
            $this->actingAs($admin)->get(route('admin-boosters.show', ['booster' => $booster->nickname])),
            'Booster Profile '.$booster->publicIdentity('Booster').' | GGWP-Boost'
        );

        $this->assertPageTitle(
            $this->actingAs($admin)->get(route('admin-orders.edit', ['order' => $order])),
            sprintf('Edit Order #%s | GGWP-Boost', $order->order_number)
        );
    }

    protected function assertPageTitle(TestResponse $response, string $expectedTitle): void
    {
        $response->assertOk();

        $html = $response->getContent();
        $titleCount = preg_match_all('/<title\b[^>]*>.*?<\/title>/is', $html, $matches);

        $this->assertSame(1, $titleCount, 'Expected exactly one <title> tag.');
        $this->assertSame($expectedTitle, html_entity_decode(strip_tags($matches[0][0] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
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
            'discord' => 'customer#1234',
            'assigned_at' => $booster ? now() : null,
        ]);
    }
}
