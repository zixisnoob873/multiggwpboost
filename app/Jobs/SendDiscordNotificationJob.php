<?php

namespace App\Jobs;

use App\Models\ContactMessage;
use App\Models\DiscordNotificationDispatch;
use App\Services\Discord\DiscordWebhookClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SendDiscordNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $timeout = 30;

    public function __construct(public readonly int $dispatchId)
    {
        $this->onQueue((string) config('payments.discord.queue', 'notifications'));
    }

    public function backoff(): array
    {
        return [10, 60, 300, 600];
    }

    public function handle(DiscordWebhookClient $client): void
    {
        $dispatch = $this->lockDispatchForSend();

        if (! $dispatch) {
            return;
        }

        $response = $client->send(
            config($dispatch->webhook_config_key),
            (array) $dispatch->payload,
            array_merge((array) $dispatch->context, [
                'dispatch_id' => $dispatch->id,
                'fingerprint' => $dispatch->fingerprint,
                'message_type' => $dispatch->message_type,
            ])
        );

        if ($response && $response->successful()) {
            DiscordNotificationDispatch::query()
                ->whereKey($dispatch->id)
                ->update([
                    'status' => DiscordNotificationDispatch::STATUS_SENT,
                    'sent_at' => now(),
                    'last_error' => null,
                ]);

            $this->syncRelatedModels($dispatch, true);

            return;
        }

        $error = $response
            ? 'Discord webhook returned HTTP '.$response->status().'.'
            : 'Discord webhook returned no response.';

        DiscordNotificationDispatch::query()
            ->whereKey($dispatch->id)
            ->update([
                'status' => DiscordNotificationDispatch::STATUS_FAILED,
                'last_error' => $error,
            ]);

        $this->syncRelatedModels($dispatch, false, $error);

        throw new RuntimeException($error);
    }

    public function failed(\Throwable $exception): void
    {
        DiscordNotificationDispatch::query()
            ->whereKey($this->dispatchId)
            ->where('status', '!=', DiscordNotificationDispatch::STATUS_SENT)
            ->update([
                'status' => DiscordNotificationDispatch::STATUS_FAILED,
                'last_error' => $exception->getMessage(),
            ]);
    }

    protected function lockDispatchForSend(): ?DiscordNotificationDispatch
    {
        return DB::transaction(function () {
            $dispatch = DiscordNotificationDispatch::query()
                ->lockForUpdate()
                ->find($this->dispatchId);

            if (! $dispatch) {
                return null;
            }

            if ($dispatch->status === DiscordNotificationDispatch::STATUS_SENT) {
                return null;
            }

            if ($dispatch->status === DiscordNotificationDispatch::STATUS_PROCESSING
                && $dispatch->updated_at
                && $dispatch->updated_at->gt(now()->subMinutes(max(1, (int) config('payments.discord.retry_failed_after_minutes', 10))))) {
                return null;
            }

            $dispatch->forceFill([
                'status' => DiscordNotificationDispatch::STATUS_PROCESSING,
                'attempts' => ((int) $dispatch->attempts) + 1,
            ])->save();

            return $dispatch->refresh();
        }, 3);
    }

    protected function syncRelatedModels(DiscordNotificationDispatch $dispatch, bool $sent, ?string $error = null): void
    {
        $contactMessageId = data_get($dispatch->context, 'contact_message_id');

        if ($contactMessageId) {
            ContactMessage::query()
                ->whereKey($contactMessageId)
                ->update([
                    'status' => $sent ? 'sent' : 'failed',
                ]);
        }
    }
}
