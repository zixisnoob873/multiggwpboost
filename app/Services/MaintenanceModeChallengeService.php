<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class MaintenanceModeChallengeService
{
    public function start(User $admin, bool $enabled): array
    {
        $token = (string) Str::uuid();
        $payload = [
            'admin_id' => $admin->getKey(),
            'enabled' => $enabled,
            'step' => 1,
            'captcha' => null,
        ];

        $this->store($token, $payload);

        return $this->flowPayload($token);
    }

    public function advanceConfirmation(User $admin, string $token, bool $enabled): array
    {
        return $this->transition($admin, $token, $enabled, 1, function (array $payload): array {
            $payload['step'] = 2;
            $payload['captcha'] = $this->captcha();

            return [
                'payload' => $payload,
                'response' => [
                    'valid' => true,
                    'step' => 2,
                    'captcha' => $payload['captcha'],
                    'expires_in' => $this->ttlSeconds(),
                ],
            ];
        });
    }

    public function verifyCaptcha(User $admin, string $token, bool $enabled, string $captcha): array
    {
        return $this->transition($admin, $token, $enabled, 2, function (array $payload) use ($captcha): array {
            $expectedCaptcha = (string) ($payload['captcha'] ?? '');

            if (! hash_equals($expectedCaptcha, trim($captcha))) {
                $payload['captcha'] = $this->captcha();

                return [
                    'payload' => $payload,
                    'response' => [
                        'valid' => false,
                        'reason' => 'captcha_mismatch',
                        'step' => 2,
                        'captcha' => $payload['captcha'],
                        'expires_in' => $this->ttlSeconds(),
                    ],
                ];
            }

            $payload['step'] = 3;

            return [
                'payload' => $payload,
                'response' => [
                    'valid' => true,
                    'step' => 3,
                    'expires_in' => $this->ttlSeconds(),
                ],
            ];
        });
    }

    public function advancePasswordVerified(User $admin, string $token, bool $enabled): array
    {
        return $this->transition($admin, $token, $enabled, 3, function (array $payload): array {
            $payload['step'] = 4;

            return [
                'payload' => $payload,
                'response' => [
                    'valid' => true,
                    'step' => 4,
                    'expires_in' => $this->ttlSeconds(),
                ],
            ];
        });
    }

    public function authorizeStep(User $admin, string $token, bool $enabled, int $expectedStep): array
    {
        $verification = $this->verifyFlow($admin, $token, $enabled, $expectedStep);

        if (! ($verification['valid'] ?? false)) {
            return $verification;
        }

        return [
            'valid' => true,
            'step' => $expectedStep,
        ];
    }

    public function authorizeFinalization(User $admin, string $token, bool $enabled): array
    {
        $verification = $this->verifyFlow($admin, $token, $enabled, 4);

        if (! ($verification['valid'] ?? false)) {
            return $verification;
        }

        Cache::forget($this->cacheKey($token));

        return [
            'valid' => true,
            'step' => 4,
        ];
    }

    protected function transition(
        User $admin,
        string $token,
        bool $enabled,
        int $expectedStep,
        callable $mutator,
    ): array {
        $verification = $this->verifyFlow($admin, $token, $enabled, $expectedStep);

        if (! ($verification['valid'] ?? false)) {
            return $verification;
        }

        $result = $mutator($verification['payload']);
        $payload = $result['payload'] ?? $verification['payload'];

        $this->store($token, $payload);

        return $result['response'] ?? ['valid' => true];
    }

    protected function verifyFlow(User $admin, string $token, bool $enabled, int $expectedStep): array
    {
        $payload = Cache::get($this->cacheKey($token));

        if (! is_array($payload)) {
            return [
                'valid' => false,
                'reason' => 'expired',
            ];
        }

        if ((int) ($payload['admin_id'] ?? 0) !== (int) $admin->getKey()) {
            Cache::forget($this->cacheKey($token));

            return [
                'valid' => false,
                'reason' => 'admin_mismatch',
            ];
        }

        if ((bool) ($payload['enabled'] ?? null) !== $enabled) {
            Cache::forget($this->cacheKey($token));

            return [
                'valid' => false,
                'reason' => 'state_mismatch',
            ];
        }

        if ((int) ($payload['step'] ?? 0) !== $expectedStep) {
            return [
                'valid' => false,
                'reason' => 'step_mismatch',
                'current_step' => (int) ($payload['step'] ?? 0),
            ];
        }

        return [
            'valid' => true,
            'payload' => $payload,
        ];
    }

    protected function store(string $token, array $payload): void
    {
        Cache::put($this->cacheKey($token), $payload, now()->addSeconds($this->ttlSeconds()));
    }

    protected function flowPayload(string $token): array
    {
        return [
            'token' => $token,
            'expires_in' => $this->ttlSeconds(),
        ];
    }

    protected function cacheKey(string $token): string
    {
        return 'maintenance-mode:challenge:'.$token;
    }

    protected function ttlSeconds(): int
    {
        return 600;
    }

    protected function captcha(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }
}
