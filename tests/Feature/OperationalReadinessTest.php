<?php

namespace Tests\Feature;

use App\Jobs\SendDiscordNotificationJob;
use App\Models\BoosterApplication;
use App\Models\ContactMessage;
use App\Models\DiscordNotificationDispatch;
use App\Models\Order;
use App\Models\User;
use App\Services\Discord\DiscordNotifier;
use App\Services\Discord\DiscordWebhookClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class OperationalReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_readiness_endpoint_returns_minimal_status_only(): void
    {
        $response = $this->getJson(route('health.ready'));

        $response->assertOk();
        $response->assertJsonPath('status', 'ready');
        $response->assertJsonMissingPath('checks');
        $response->assertJsonMissingPath('checkedAt');
    }

    public function test_unsigned_internal_readiness_endpoint_is_blocked(): void
    {
        $this->getJson(route('health.ready.internal'))->assertForbidden();
    }

    public function test_signed_internal_readiness_endpoint_reports_database_cache_and_storage_checks(): void
    {
        Storage::fake('public');
        config()->set('filesystems.default', 'public');
        config()->set('app.debug', false);

        $response = $this->getJson(URL::temporarySignedRoute('health.ready.internal', now()->addMinutes(5)));

        $response->assertOk();
        $response->assertJsonPath('status', 'ready');
        $response->assertJsonPath('checks.database.ok', true);
        $response->assertJsonPath('checks.cache.ok', true);
        $response->assertJsonPath('checks.storage.ok', true);
        $response->assertJsonPath('checks.public_storage_link.ok', true);
        $response->assertJsonMissingPath('checks.public_storage_link.path');
        $response->assertJsonMissingPath('checks.public_storage_link.target');
    }

    public function test_public_readiness_endpoint_is_rate_limited(): void
    {
        RateLimiter::clear('health-readiness-ip:127.0.0.1');

        for ($attempt = 0; $attempt < 30; $attempt++) {
            $this->getJson(route('health.ready'))->assertOk();
        }

        $this->getJson(route('health.ready'))->assertTooManyRequests();
    }

    public function test_booster_application_form_persists_records_even_when_the_webhook_is_unavailable(): void
    {
        config()->set('services.discord.webhook_booster_applications', null);

        $response = $this->from(route('become-booster'))->post(route('become-booster.submit'), [
            'name' => 'Demo Booster',
            'nickname' => 'RadiantCarry',
            'email' => 'booster@example.com',
            'current_rank' => 'Immortal I',
            'peak_rank' => 'Radiant',
            'average_time' => '6 hours',
            'discord' => 'booster#1234',
            'main_account_tracker' => 'https://tracker.example.com/main-account',
            'marketplace_profile' => 'https://tracker.example.com/marketplace',
            'regions' => ['EU', 'NA'],
        ]);

        $response->assertRedirect(route('become-booster'));
        $response->assertSessionHas('status');

        $application = BoosterApplication::query()
            ->where('nickname', 'RadiantCarry')
            ->firstOrFail();

        $this->assertSame('booster@example.com', $application->email);
        $this->assertSame('new', $application->status);
    }

    public function test_contact_form_persists_messages_even_when_the_webhook_is_unavailable(): void
    {
        config()->set('services.discord.webhook_contact', null);

        $response = $this->from(route('contact'))->post(route('contact.submit'), [
            'name' => 'Demo User',
            'email' => 'demo@example.com',
            'order_reference' => 'ORD-100',
            'message' => 'Need help with my order because the queue changed unexpectedly.',
            'website' => '',
        ]);

        $response->assertRedirect(route('contact'));
        $response->assertSessionHas('status');

        $message = ContactMessage::query()
            ->where('order_ref', 'ORD-100')
            ->firstOrFail();

        $this->assertSame('demo@example.com', $message->email);
        $this->assertSame('received', $message->status);
    }

    public function test_duplicate_contact_notifications_are_deduplicated_into_one_dispatch(): void
    {
        Queue::fake();
        config()->set('services.discord.webhook_contact', 'https://discord.test/webhook/contact');

        $firstMessage = ContactMessage::query()->create([
            'name' => 'Demo User',
            'email' => 'demo@example.com',
            'order_ref' => 'ORD-100',
            'message' => 'Need help with my order.',
            'status' => 'queued',
        ]);
        $secondMessage = ContactMessage::query()->create([
            'name' => 'Demo User',
            'email' => 'demo@example.com',
            'order_ref' => 'ORD-100',
            'message' => 'Need help with my order.',
            'status' => 'queued',
        ]);

        $notifier = app(DiscordNotifier::class);
        $notifier->queueContactMessage($firstMessage);
        $notifier->queueContactMessage($secondMessage);

        $this->assertSame(1, DiscordNotificationDispatch::query()->count());
        Queue::assertPushed(SendDiscordNotificationJob::class, 1);
    }

    public function test_order_notifications_are_fanned_out_to_all_configured_order_webhooks(): void
    {
        Queue::fake();
        config()->set('services.discord.webhook_orders', 'https://discord.test/webhook/orders');
        config()->set('services.discord.webhook_order_channels', [
            'duplicate_primary' => 'https://discord.test/webhook/orders',
            'ops' => 'https://discord.test/webhook/orders-ops',
            'support' => 'https://discord.test/webhook/orders-support',
            'empty' => '',
        ]);

        $order = $this->createDiscordOrder();

        app(DiscordNotifier::class)->queueOrderCreated($order);

        $dispatches = DiscordNotificationDispatch::query()
            ->orderBy('id')
            ->get();

        $this->assertCount(3, $dispatches);
        $this->assertSame([
            'services.discord.webhook_orders',
            'services.discord.webhook_order_channels.ops',
            'services.discord.webhook_order_channels.support',
        ], $dispatches->pluck('webhook_config_key')->all());
        $this->assertCount(3, $dispatches->pluck('fingerprint')->unique());
        Queue::assertPushed(SendDiscordNotificationJob::class, 3);
    }

    public function test_order_notifications_support_channel_lists_without_the_legacy_single_webhook(): void
    {
        Queue::fake();
        config()->set('services.discord.webhook_orders', null);
        config()->set('services.discord.webhook_order_channels', [
            'primary' => 'https://discord.test/webhook/orders-primary',
            'secondary' => 'https://discord.test/webhook/orders-secondary',
        ]);

        $order = $this->createDiscordOrder('ORD-DISCORD-CHANNELS');

        app(DiscordNotifier::class)->queueOrderCreated($order);

        $this->assertSame([
            'services.discord.webhook_order_channels.primary',
            'services.discord.webhook_order_channels.secondary',
        ], DiscordNotificationDispatch::query()->orderBy('id')->pluck('webhook_config_key')->all());
        Queue::assertPushed(SendDiscordNotificationJob::class, 2);
    }

    public function test_discord_notification_job_resolves_channel_specific_webhook_config_keys(): void
    {
        Http::fake([
            'https://discord.test/webhook/orders-secondary' => Http::response('', 204),
        ]);
        config()->set('services.discord.webhook_order_channels', [
            'secondary' => 'https://discord.test/webhook/orders-secondary',
        ]);

        $dispatch = DiscordNotificationDispatch::query()->create([
            'fingerprint' => 'order-created:secondary-channel',
            'webhook_config_key' => 'services.discord.webhook_order_channels.secondary',
            'message_type' => 'OrderCreatedMessage',
            'payload' => ['username' => 'GGWP Orders', 'embeds' => []],
            'context' => ['order_id' => 123],
            'status' => DiscordNotificationDispatch::STATUS_PENDING,
        ]);

        (new SendDiscordNotificationJob($dispatch->id))->handle(app(DiscordWebhookClient::class));

        Http::assertSent(fn ($request) => $request->url() === 'https://discord.test/webhook/orders-secondary');
        $this->assertSame(DiscordNotificationDispatch::STATUS_SENT, $dispatch->refresh()->status);
    }

    public function test_retry_command_requeues_stale_failed_discord_dispatches(): void
    {
        Queue::fake();

        $dispatch = DiscordNotificationDispatch::query()->create([
            'fingerprint' => 'contact:stale',
            'webhook_config_key' => 'services.discord.webhook_contact',
            'message_type' => 'ContactMessage',
            'payload' => ['username' => 'Test', 'embeds' => []],
            'context' => [],
            'status' => DiscordNotificationDispatch::STATUS_FAILED,
            'attempts' => 2,
            'last_error' => 'Timeout',
        ]);

        $dispatch->forceFill([
            'updated_at' => now()->subMinutes(15),
        ])->save();

        $this->artisan('discord:retry-dispatches --minutes=5')->assertSuccessful();

        Queue::assertPushed(SendDiscordNotificationJob::class, function (SendDiscordNotificationJob $job) use ($dispatch) {
            return $job->dispatchId === $dispatch->id;
        });
    }

    protected function createDiscordOrder(string $orderNumber = 'ORD-DISCORD-MULTI'): Order
    {
        $user = User::factory()->create([
            'role' => 'customer',
            'account_status' => 'active',
        ]);

        return Order::query()->create([
            'user_id' => $user->id,
            'order_number' => $orderNumber,
            'product' => 'Rank Boosting',
            'status' => 'Pending',
            'payment_status' => 'paid',
            'price_cents' => 1299,
            'currency' => 'USD',
            'details' => [
                'order' => [
                    'orderType' => 'Rank Boosting',
                    'currentDivision' => 'Gold I',
                    'targetDivision' => 'Platinum I',
                    'selectedAddons' => [],
                ],
            ],
            'metadata' => [
                'customer' => [
                    'firstName' => 'Demo',
                    'lastName' => 'Customer',
                    'email' => $user->email,
                ],
            ],
            'contact_method' => 'discord',
            'discord' => 'demo#1234',
        ]);
    }
}
