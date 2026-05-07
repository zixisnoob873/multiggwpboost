<?php

namespace App\Services\Mail;

use App\Mail\Transactional\AccountCreatedMail;
use App\Mail\Transactional\AccountReactivatedMail;
use App\Mail\Transactional\AccountSuspendedMail;
use App\Models\User;

class AccountLifecycleEmailNotifier
{
    public function __construct(protected TransactionalMailDispatcher $transactionalMailDispatcher) {}

    public function queueAccountCreated(User $user, string $source = 'admin'): bool
    {
        $createdAt = $user->created_at ?? now();
        $payload = $this->basePayload($user) + [
            'account' => [
                'source' => $source,
                'created_at' => $createdAt->toIso8601String(),
                'created_at_formatted' => $createdAt->format('M j, Y g:i A T'),
            ],
        ];

        return $this->transactionalMailDispatcher->queue(
            $user->email,
            new AccountCreatedMail($payload),
            $user->fullIdentity('User'),
            $this->fingerprint('account-created', $user, [
                'source' => $source,
                'created_at' => $createdAt->toIso8601String(),
            ]),
            ['email_type' => 'account_created', 'user_id' => $user->getKey(), 'source' => $source],
        );
    }

    public function queueStatusChanged(User $user, ?string $previousStatus): bool
    {
        $currentStatus = (string) $user->account_status;

        if ($previousStatus === $currentStatus) {
            return false;
        }

        if ($currentStatus === 'suspended') {
            $changedAt = $user->updated_at ?? now();
            $payload = $this->basePayload($user) + [
                'account' => [
                    'previous_status' => $previousStatus,
                    'status' => $currentStatus,
                    'changed_at' => $changedAt->toIso8601String(),
                    'changed_at_formatted' => $changedAt->format('M j, Y g:i A T'),
                    'reason' => null,
                ],
            ];

            return $this->transactionalMailDispatcher->queue(
                $user->email,
                new AccountSuspendedMail($payload),
                $user->fullIdentity('User'),
                $this->fingerprint('account-status', $user, [
                    'previous_status' => $previousStatus,
                    'current_status' => $currentStatus,
                    'changed_at' => $changedAt->toIso8601String(),
                ]),
                ['email_type' => 'account_suspended', 'user_id' => $user->getKey()],
            );
        }

        if ($previousStatus === 'suspended' && $currentStatus === 'active') {
            $changedAt = $user->updated_at ?? now();
            $payload = $this->basePayload($user) + [
                'account' => [
                    'previous_status' => $previousStatus,
                    'status' => $currentStatus,
                    'changed_at' => $changedAt->toIso8601String(),
                    'changed_at_formatted' => $changedAt->format('M j, Y g:i A T'),
                ],
            ];

            return $this->transactionalMailDispatcher->queue(
                $user->email,
                new AccountReactivatedMail($payload),
                $user->fullIdentity('User'),
                $this->fingerprint('account-status', $user, [
                    'previous_status' => $previousStatus,
                    'current_status' => $currentStatus,
                    'changed_at' => $changedAt->toIso8601String(),
                ]),
                ['email_type' => 'account_reactivated', 'user_id' => $user->getKey()],
            );
        }

        return false;
    }

    protected function basePayload(User $user): array
    {
        $role = User::normalizeRole($user->role);

        return [
            'user' => [
                'id' => $user->getKey(),
                'name' => $user->fullIdentity('User'),
                'email' => $user->email,
                'role' => $role,
                'role_label' => $this->roleLabel($role),
                'account_status' => $user->account_status,
            ],
            'links' => [
                'login_url' => route('login'),
                'dashboard_url' => $this->dashboardUrlFor($role),
                'support_url' => route('contact'),
                'support_email' => $this->supportEmail(),
            ],
            'branding' => $this->brandingPayload(),
        ];
    }

    protected function brandingPayload(): array
    {
        $branding = [
            'app_name' => config('app.name', 'GGWP Boost'),
        ];

        $logoUrl = trim((string) config('mail.logo_url', ''));

        if ($logoUrl !== '') {
            $branding['logo_url'] = $logoUrl;
        }

        return $branding;
    }

    protected function dashboardUrlFor(string $role): string
    {
        return match ($role) {
            User::ROLE_BOOSTER => route('booster-dashboard'),
            default => route('customer-dashboard'),
        };
    }

    protected function roleLabel(string $role): string
    {
        return match ($role) {
            User::ROLE_BOOSTER => 'Booster',
            User::ROLE_SUPER_ADMIN => 'Admin',
            default => 'Customer',
        };
    }

    protected function supportEmail(): ?string
    {
        $email = trim((string) (config('footer.support.email') ?? config('mail.from.address') ?? ''));

        return $email !== '' ? $email : null;
    }

    protected function fingerprint(string $event, User $user, array $context): string
    {
        return hash('sha256', json_encode([
            'event' => $event,
            'user_id' => $user->getKey(),
            ...$context,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}
