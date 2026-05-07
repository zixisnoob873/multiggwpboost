<?php

namespace App\Services\Discord;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DiscordWebhookClient
{
    public function send(?string $webhookUrl, array $payload, array $context = []): ?Response
    {
        if (! filled($webhookUrl)) {
            return null;
        }

        $timeout = max(1, (int) config('services.discord.timeout', 5));
        $retries = max(0, (int) config('services.discord.retries', 2));
        $retrySleepMs = max(0, (int) config('services.discord.retry_sleep_ms', 250));
        $logContext = array_merge($context, [
            'webhook_host' => parse_url($webhookUrl, PHP_URL_HOST) ?: null,
            'timeout_seconds' => $timeout,
            'retries' => $retries,
        ]);

        try {
            $response = Http::timeout($timeout)
                ->retry($retries + 1, $retrySleepMs, null, false)
                ->post($webhookUrl, $payload);
        } catch (ConnectionException $exception) {
            Log::channel('discord')->error('Discord webhook request failed after retry attempts.', [
                ...$logContext,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }

        if ($response->failed()) {
            Log::channel('discord')->warning('Discord webhook request returned a non-success response.', [
                ...$logContext,
                'status' => $response->status(),
                'response' => Str::limit($response->body(), 500),
            ]);
        }

        return $response;
    }
}
