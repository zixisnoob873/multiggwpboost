<?php

namespace App\Services\Discord;

use App\Contracts\Notifications\DiscordMessage;
use App\Jobs\SendDiscordNotificationJob;
use App\Models\BoosterApplication;
use App\Models\ContactMessage;
use App\Models\DiscordNotificationDispatch;
use App\Models\Order;
use App\Models\User;
use App\Models\WithdrawalRequest;
use App\Notifications\Discord\BoosterApplicationMessage;
use App\Notifications\Discord\ContactMessage as ContactDiscordMessage;
use App\Notifications\Discord\OrderCreatedMessage;
use App\Notifications\Discord\WithdrawalRequestMessage;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DiscordNotifier
{
    public function queue(DiscordMessage $message, string $fingerprint, array $context = []): DiscordNotificationDispatch
    {
        return $this->queueForConfigKey($message, $fingerprint, $context, $message->webhookConfigKey());
    }

    protected function queueForConfigKey(DiscordMessage $message, string $fingerprint, array $context, string $configKey): DiscordNotificationDispatch
    {
        $dispatch = DiscordNotificationDispatch::query()->firstOrCreate(
            ['fingerprint' => $fingerprint],
            [
                'webhook_config_key' => $configKey,
                'message_type' => $message::class,
                'payload' => [
                    'username' => $message->username(),
                    'embeds' => $message->embeds(),
                ],
                'context' => array_merge($context, [
                    'webhook_config_key' => $configKey,
                    'message_type' => class_basename($message),
                ]),
                'status' => DiscordNotificationDispatch::STATUS_PENDING,
            ]
        );

        if (! $this->isConfigured($configKey)) {
            $dispatch->forceFill([
                'status' => DiscordNotificationDispatch::STATUS_FAILED,
                'last_error' => 'Discord webhook is not configured.',
            ])->save();

            Log::channel('discord')->warning('Skipped Discord notification because the webhook is not configured.', [
                'fingerprint' => $fingerprint,
                'message_type' => $message::class,
                'webhook_config_key' => $configKey,
            ]);

            return $dispatch->refresh();
        }

        if ($dispatch->wasRecentlyCreated || $this->shouldQueueDispatch($dispatch)) {
            SendDiscordNotificationJob::dispatch($dispatch->id)->afterCommit();
        }

        return $dispatch;
    }

    public function queueOrderCreated(Order $order): DiscordNotificationDispatch
    {
        $message = new OrderCreatedMessage($order);
        $baseFingerprint = 'order-created:'.$order->getKey();
        $dispatches = [];

        foreach ($this->orderWebhookConfigKeys() as $index => $configKey) {
            $dispatches[] = $this->queueForConfigKey(
                $message,
                $this->scopedWebhookFingerprint($baseFingerprint, $configKey, $index),
                [
                    'order_id' => $order->getKey(),
                    'webhook_destination' => $this->webhookDestinationLabel($configKey),
                ],
                $configKey
            );
        }

        return $dispatches[0];
    }

    public function queueBoosterApplication(BoosterApplication $application): DiscordNotificationDispatch
    {
        return $this->queue(
            new BoosterApplicationMessage($application->toArray()),
            $this->publicFormFingerprint('booster-application', [
                'email' => $application->email,
                'discord' => $application->discord,
                'current_rank' => $application->current_rank,
                'peak_rank' => $application->peak_rank,
                'regions' => Arr::wrap($application->regions),
            ]),
            [
                'booster_application_id' => $application->getKey(),
            ]
        );
    }

    public function queueContactMessage(ContactMessage $contactMessage): DiscordNotificationDispatch
    {
        return $this->queue(
            new ContactDiscordMessage([
                'name' => $contactMessage->name,
                'email' => $contactMessage->email,
                'order_reference' => $contactMessage->order_ref,
                'message' => $contactMessage->message,
            ]),
            $this->publicFormFingerprint('contact-message', [
                'email' => $contactMessage->email,
                'order_reference' => $contactMessage->order_ref,
                'message' => $contactMessage->message,
            ]),
            [
                'contact_message_id' => $contactMessage->getKey(),
            ]
        );
    }

    public function queueWithdrawalRequest(User $user, WithdrawalRequest $withdrawalRequest, int $requestedAmountCents, int $availableBalanceCents): DiscordNotificationDispatch
    {
        return $this->queue(
            new WithdrawalRequestMessage(
                $user,
                $withdrawalRequest,
                $requestedAmountCents,
                $availableBalanceCents
            ),
            'withdrawal-request:'.$withdrawalRequest->getKey(),
            [
                'withdrawal_request_id' => $withdrawalRequest->getKey(),
                'booster_id' => $user->getKey(),
            ]
        );
    }

    public function isConfigured(string $configKey): bool
    {
        return filled(config($configKey));
    }

    public function hasBoosterApplicationWebhook(): bool
    {
        return $this->isConfigured('services.discord.webhook_booster_applications');
    }

    public function hasContactWebhook(): bool
    {
        return $this->isConfigured('services.discord.webhook_contact');
    }

    protected function orderWebhookConfigKeys(): array
    {
        $channels = config('services.discord.webhook_order_channels', []);
        $keys = [];
        $seenUrls = [];
        $primaryUrl = trim((string) config('services.discord.webhook_orders'));

        if ($primaryUrl !== '') {
            $keys[] = 'services.discord.webhook_orders';
            $seenUrls[$primaryUrl] = true;
        }

        if (is_array($channels)) {
            foreach ($channels as $name => $url) {
                $normalizedUrl = trim((string) $url);

                if ($normalizedUrl === '' || isset($seenUrls[$normalizedUrl])) {
                    continue;
                }

                $seenUrls[$normalizedUrl] = true;
                $keys[] = 'services.discord.webhook_order_channels.'.$name;
            }
        }

        return $keys === [] ? ['services.discord.webhook_orders'] : $keys;
    }

    protected function scopedWebhookFingerprint(string $baseFingerprint, string $configKey, int $index): string
    {
        if ($index === 0) {
            return $baseFingerprint;
        }

        $destination = Str::slug($this->webhookDestinationLabel($configKey)) ?: 'destination';

        return $baseFingerprint.':'.$destination.':'.substr(sha1($configKey), 0, 8);
    }

    protected function webhookDestinationLabel(string $configKey): string
    {
        return Str::afterLast($configKey, '.');
    }

    protected function publicFormFingerprint(string $prefix, array $values): string
    {
        $normalized = collect($values)
            ->map(function ($value) {
                if (is_array($value)) {
                    $items = array_map(fn ($item) => Str::lower(trim((string) $item)), $value);
                    sort($items);

                    return $items;
                }

                return Str::lower(preg_replace('/\s+/', ' ', trim((string) $value)));
            })
            ->toArray();

        $bucketSeconds = max(60, (int) config('payments.discord.public_form_dedupe_window_minutes', 15) * 60);
        $bucket = (int) floor(time() / $bucketSeconds);

        return $prefix.':'.sha1(json_encode([
            'bucket' => $bucket,
            'values' => $normalized,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    protected function shouldQueueDispatch(DiscordNotificationDispatch $dispatch): bool
    {
        if ($dispatch->status === DiscordNotificationDispatch::STATUS_FAILED) {
            return true;
        }

        if (! in_array($dispatch->status, [
            DiscordNotificationDispatch::STATUS_PENDING,
            DiscordNotificationDispatch::STATUS_PROCESSING,
        ], true)) {
            return false;
        }

        $retryAfter = now()->subMinutes(max(1, (int) config('payments.discord.retry_failed_after_minutes', 10)));

        return ! $dispatch->updated_at || $dispatch->updated_at->lte($retryAfter);
    }
}
