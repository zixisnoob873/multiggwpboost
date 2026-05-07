<?php

namespace App\Services\Payments;

use Illuminate\Support\Facades\Http;
use LogicException;

class CryptomusClient
{
    public function createInvoice(array $payload): array
    {
        return $this->request('/v1/payment', $payload);
    }

    public function paymentInfo(array $payload): array
    {
        return $this->request('/v1/payment/info', $payload);
    }

    public function verifyWebhookSignature(array $payload): bool
    {
        $sign = (string) ($payload['sign'] ?? '');

        if ($sign === '') {
            return false;
        }

        unset($payload['sign']);

        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if (! is_string($encoded)) {
            return false;
        }

        return hash_equals($this->sign($encoded), $sign);
    }

    protected function request(string $path, array $payload): array
    {
        $merchantId = $this->merchantId();
        $apiKey = $this->apiKey();
        $encoded = $this->encodePayload($payload);

        $response = Http::baseUrl($this->baseUrl())
            ->acceptJson()
            ->timeout($this->timeoutSeconds())
            ->withHeaders([
                'merchant' => $merchantId,
                'sign' => md5(base64_encode($encoded).$apiKey),
                'Content-Type' => 'application/json',
            ])
            ->withBody($encoded, 'application/json')
            ->post($path);

        $response->throw();

        $decoded = $response->json();

        if (! is_array($decoded)) {
            throw new LogicException('Cryptomus returned an invalid JSON payload.');
        }

        if (($decoded['state'] ?? 1) !== 0) {
            $message = $this->extractErrorMessage($decoded) ?? 'Cryptomus rejected the request.';

            throw new LogicException($message);
        }

        $result = $decoded['result'] ?? null;

        if (! is_array($result)) {
            throw new LogicException('Cryptomus did not return a payment result.');
        }

        return $result;
    }

    protected function encodePayload(array $payload): string
    {
        $encoded = json_encode($payload);

        if (! is_string($encoded)) {
            throw new LogicException('Unable to encode the Cryptomus request payload.');
        }

        return $encoded;
    }

    protected function extractErrorMessage(array $payload): ?string
    {
        $errors = $payload['errors'] ?? null;

        if (is_array($errors)) {
            $first = reset($errors);

            if (is_string($first) && trim($first) !== '') {
                return trim($first);
            }
        }

        $message = $payload['message'] ?? null;

        return is_string($message) && trim($message) !== '' ? trim($message) : null;
    }

    protected function sign(string $payload): string
    {
        return md5(base64_encode($payload).$this->apiKey());
    }

    protected function merchantId(): string
    {
        $merchantId = trim((string) config('services.cryptomus.merchant_id', ''));

        if ($merchantId === '') {
            throw new LogicException('Cryptomus merchant ID is not configured.');
        }

        return $merchantId;
    }

    protected function apiKey(): string
    {
        $apiKey = trim((string) config('services.cryptomus.api_key', ''));

        if ($apiKey === '') {
            throw new LogicException('Cryptomus API key is not configured.');
        }

        return $apiKey;
    }

    protected function baseUrl(): string
    {
        $baseUrl = rtrim((string) config('services.cryptomus.base_url', 'https://api.cryptomus.com'), '/');
        $parts = parse_url($baseUrl);

        if (! is_array($parts) || blank($parts['scheme'] ?? null) || blank($parts['host'] ?? null)) {
            throw new LogicException('Cryptomus base URL is not a valid absolute URL.');
        }

        if (app()->environment(['production', 'staging'])) {
            $scheme = strtolower((string) $parts['scheme']);
            $host = $this->normalizedHost((string) $parts['host']);
            $allowedHosts = $this->allowedHosts();

            if ($scheme !== 'https') {
                throw new LogicException('Cryptomus base URL must use HTTPS in production-like environments.');
            }

            if (! in_array($host, $allowedHosts, true)) {
                throw new LogicException('Cryptomus base URL host is not allowed in production-like environments.');
            }
        }

        return $baseUrl;
    }

    protected function timeoutSeconds(): int
    {
        return max(5, (int) config('services.cryptomus.timeout', 15));
    }

    /**
     * @return array<int, string>
     */
    protected function allowedHosts(): array
    {
        return array_values(array_filter(array_map(
            fn (mixed $host): string => $this->normalizedHost((string) $host),
            (array) config('services.cryptomus.allowed_hosts', ['api.cryptomus.com'])
        )));
    }

    protected function normalizedHost(string $host): string
    {
        return strtolower(trim($host, "[] \t\n\r\0\x0B."));
    }
}
