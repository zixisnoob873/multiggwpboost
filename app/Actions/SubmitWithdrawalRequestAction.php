<?php

namespace App\Actions;

use App\Models\User;
use App\Models\WithdrawalRequest;
use App\Services\BoosterWalletService;
use App\Services\Discord\DiscordNotifier;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SubmitWithdrawalRequestAction
{
    public function __construct(
        protected BoosterWalletService $boosterWalletService,
        protected DiscordNotifier $discordNotifier
    ) {}

    public function execute(User $user, float $amount): WithdrawalRequest
    {
        $requestedAmountCents = (int) round($amount * 100);
        $availableBalanceCents = 0;

        $withdrawalRequest = $this->boosterWalletService->withinLockedWallet($user, function (User $lockedUser, array $summary) use ($requestedAmountCents, &$availableBalanceCents) {
            $availableBalanceCents = (int) ($summary['available_balance_cents'] ?? 0);

            if ($requestedAmountCents > $availableBalanceCents) {
                throw new HttpException(422, 'Requested amount exceeds your available balance.');
            }

            return WithdrawalRequest::create([
                'booster_id' => $lockedUser->id,
                'amount_cents' => $requestedAmountCents,
                'status' => WithdrawalRequest::STATUS_PENDING,
            ]);
        });

        $this->discordNotifier->queueWithdrawalRequest(
            $user,
            $withdrawalRequest,
            $requestedAmountCents,
            $availableBalanceCents
        );

        return $withdrawalRequest;
    }
}
