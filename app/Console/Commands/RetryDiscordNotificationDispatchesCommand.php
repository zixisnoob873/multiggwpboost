<?php

namespace App\Console\Commands;

use App\Jobs\SendDiscordNotificationJob;
use App\Models\DiscordNotificationDispatch;
use Illuminate\Console\Command;

class RetryDiscordNotificationDispatchesCommand extends Command
{
    protected $signature = 'discord:retry-dispatches {--minutes= : Override the retry window in minutes}';

    protected $description = 'Requeue stale or failed Discord notification dispatches.';

    public function handle(): int
    {
        $minutes = (int) ($this->option('minutes') ?: config('payments.discord.retry_failed_after_minutes', 10));
        $threshold = now()->subMinutes(max(1, $minutes));

        $dispatchIds = DiscordNotificationDispatch::query()
            ->whereIn('status', [
                DiscordNotificationDispatch::STATUS_PENDING,
                DiscordNotificationDispatch::STATUS_PROCESSING,
                DiscordNotificationDispatch::STATUS_FAILED,
            ])
            ->where('updated_at', '<=', $threshold)
            ->pluck('id');

        foreach ($dispatchIds as $dispatchId) {
            SendDiscordNotificationJob::dispatch((int) $dispatchId);
        }

        $this->info('Queued '.$dispatchIds->count().' Discord notification retry job(s).');

        return self::SUCCESS;
    }
}
