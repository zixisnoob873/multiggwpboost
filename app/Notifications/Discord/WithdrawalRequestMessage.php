<?php

namespace App\Notifications\Discord;

use App\Models\User;
use App\Models\WithdrawalRequest;

class WithdrawalRequestMessage extends AbstractDiscordMessage
{
    public function __construct(
        protected User $user,
        protected WithdrawalRequest $withdrawalRequest,
        protected int $requestedAmountCents,
        protected int $availableBalanceCents
    ) {}

    public function webhookConfigKey(): string
    {
        return 'services.discord.webhook_withdrawals';
    }

    public function username(): string
    {
        return 'GGWP Withdrawal Requests';
    }

    protected function embed(): array
    {
        return [
            'title' => 'New Withdrawal Request',
            'color' => 0xDC3545,
            'fields' => [
                ['name' => 'Booster', 'value' => $this->user->name ?? 'N/A', 'inline' => true],
                ['name' => 'Email', 'value' => $this->user->email ?? 'N/A', 'inline' => true],
                ['name' => 'Request ID', 'value' => '#'.$this->withdrawalRequest->id, 'inline' => true],
                ['name' => 'Amount', 'value' => '$'.number_format($this->requestedAmountCents / 100, 2), 'inline' => true],
                ['name' => 'Available Balance', 'value' => '$'.number_format($this->availableBalanceCents / 100, 2), 'inline' => true],
                ['name' => 'Status', 'value' => 'Pending', 'inline' => true],
            ],
            'footer' => ['text' => 'GGWP Boost'],
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
