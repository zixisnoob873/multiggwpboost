<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Privacy\UserHistoryRedactionService;
use Illuminate\Console\Command;

class RedactUserHistoryCommand extends Command
{
    protected $signature = 'privacy:redact-user-history {user_id : The user ID to redact} {--scope=all : customer|booster|all}';

    protected $description = 'Redact known user identifiers from historical order notes, related order data, and chat transcripts.';

    public function handle(UserHistoryRedactionService $service): int
    {
        $user = User::withTrashed()->findOrFail((int) $this->argument('user_id'));
        $scope = (string) $this->option('scope');

        if (in_array($scope, ['customer', 'all'], true)) {
            $service->redactCustomerHistory($user);
        }

        if (in_array($scope, ['booster', 'all'], true)) {
            $service->redactBoosterHistory($user);
        }

        $this->info("Redacted historical text for user #{$user->id} using scope [{$scope}].");

        return self::SUCCESS;
    }
}
