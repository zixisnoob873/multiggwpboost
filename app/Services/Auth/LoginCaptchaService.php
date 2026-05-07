<?php

namespace App\Services\Auth;

use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class LoginCaptchaService
{
    protected const FAILED_ATTEMPTS_SESSION_KEY = 'auth.login_captcha.failed_attempts';

    protected const CHALLENGE_SESSION_KEY = 'auth.login_captcha.challenge';

    public function viewData(Request $request, ?string $email = null): array
    {
        $normalizedEmail = $this->normalizeEmail($email);

        if ($normalizedEmail === null) {
            return [
                'required' => false,
                'challenge' => null,
                'expiresInSeconds' => null,
            ];
        }

        $contextKey = $this->contextKey($normalizedEmail, $request->ip());
        $required = $this->requiresCaptcha($request->session(), $contextKey);

        if (! $required) {
            return [
                'required' => false,
                'challenge' => null,
                'expiresInSeconds' => null,
            ];
        }

        $challenge = $this->ensureChallenge($request->session(), $contextKey);

        return [
            'required' => true,
            'challenge' => $challenge['code'],
            'expiresInSeconds' => max(0, Carbon::parse($challenge['expires_at'])->diffInSeconds(now(), false) * -1),
        ];
    }

    public function requiresCaptchaForRequest(Request $request, ?string $email = null): bool
    {
        $normalizedEmail = $this->normalizeEmail($email ?? (string) $request->input('email'));

        if ($normalizedEmail === null) {
            return false;
        }

        return $this->requiresCaptcha(
            $request->session(),
            $this->contextKey($normalizedEmail, $request->ip())
        );
    }

    public function ensureValidCaptcha(Request $request, string $email, ?string $submittedCode): ?string
    {
        $normalizedEmail = $this->normalizeEmail($email);

        if ($normalizedEmail === null) {
            return null;
        }

        $contextKey = $this->contextKey($normalizedEmail, $request->ip());

        if (! $this->requiresCaptcha($request->session(), $contextKey)) {
            return null;
        }

        $sanitizedCode = preg_replace('/\D+/', '', trim((string) $submittedCode)) ?: '';

        if ($sanitizedCode === '') {
            return 'Enter the 7-digit captcha to continue.';
        }

        if (preg_match('/^\d{7}$/', $sanitizedCode) !== 1) {
            return 'The captcha must be exactly 7 digits.';
        }

        $challenge = $this->challenge($request->session());

        if (
            ! is_array($challenge)
            || ($challenge['context'] ?? null) !== $contextKey
            || $this->challengeExpired($challenge)
            || ! hash_equals((string) ($challenge['code'] ?? ''), $sanitizedCode)
        ) {
            return 'Captcha is incorrect or expired. Please try again.';
        }

        return null;
    }

    public function recordFailure(Request $request, string $email): void
    {
        $normalizedEmail = $this->normalizeEmail($email);

        if ($normalizedEmail === null) {
            return;
        }

        $session = $request->session();
        $contextKey = $this->contextKey($normalizedEmail, $request->ip());
        $attempts = (array) $session->get(self::FAILED_ATTEMPTS_SESSION_KEY, []);
        $attempts[$contextKey] = max(0, (int) ($attempts[$contextKey] ?? 0)) + 1;
        $session->put(self::FAILED_ATTEMPTS_SESSION_KEY, $attempts);

        if ($this->requiresCaptcha($session, $contextKey)) {
            $this->issueFreshChallenge($session, $contextKey);
        }
    }

    public function clear(Request $request): void
    {
        $request->session()->forget([
            self::FAILED_ATTEMPTS_SESSION_KEY,
            self::CHALLENGE_SESSION_KEY,
        ]);
    }

    protected function requiresCaptcha(Session $session, string $contextKey): bool
    {
        $attempts = (array) $session->get(self::FAILED_ATTEMPTS_SESSION_KEY, []);

        return (int) ($attempts[$contextKey] ?? 0) >= $this->threshold();
    }

    protected function ensureChallenge(Session $session, string $contextKey): array
    {
        $challenge = $this->challenge($session);

        if (
            is_array($challenge)
            && ($challenge['context'] ?? null) === $contextKey
            && ! $this->challengeExpired($challenge)
            && preg_match('/^\d{7}$/', (string) ($challenge['code'] ?? '')) === 1
        ) {
            return $challenge;
        }

        return $this->issueFreshChallenge($session, $contextKey);
    }

    protected function issueFreshChallenge(Session $session, string $contextKey): array
    {
        $challenge = [
            'context' => $contextKey,
            'code' => $this->generateCode(),
            'expires_at' => now()->addMinutes($this->expiryMinutes())->toIso8601String(),
        ];

        $session->put(self::CHALLENGE_SESSION_KEY, $challenge);

        return $challenge;
    }

    protected function challenge(Session $session): ?array
    {
        $challenge = $session->get(self::CHALLENGE_SESSION_KEY);

        return is_array($challenge) ? $challenge : null;
    }

    protected function challengeExpired(array $challenge): bool
    {
        $expiresAt = $challenge['expires_at'] ?? null;

        if (! is_string($expiresAt) || trim($expiresAt) === '') {
            return true;
        }

        try {
            return Carbon::parse($expiresAt)->isPast();
        } catch (\Throwable) {
            return true;
        }
    }

    protected function contextKey(string $email, ?string $ip): string
    {
        return $email.'|'.trim((string) $ip);
    }

    protected function normalizeEmail(?string $email): ?string
    {
        $normalized = strtolower(trim((string) $email));

        return $normalized !== '' ? $normalized : null;
    }

    protected function generateCode(): string
    {
        return str_pad((string) random_int(0, 9999999), 7, '0', STR_PAD_LEFT);
    }

    protected function threshold(): int
    {
        return max(1, (int) config('auth.login_captcha.threshold', 3));
    }

    protected function expiryMinutes(): int
    {
        return max(1, (int) config('auth.login_captcha.expiry_minutes', 10));
    }
}
