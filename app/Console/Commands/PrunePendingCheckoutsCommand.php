<?php

namespace App\Console\Commands;

use App\Services\Payments\PendingCheckoutStore;
use Illuminate\Console\Command;

class PrunePendingCheckoutsCommand extends Command
{
    protected $signature = 'pending-checkouts:prune';

    protected $description = 'Prune stale and completed pending checkout records that are past retention.';

    public function handle(PendingCheckoutStore $pendingCheckoutStore): int
    {
        $staleDeleted = $pendingCheckoutStore->staleRecordsQuery()->delete();
        $completedDeleted = $pendingCheckoutStore->completedRecordsQuery()->delete();

        $this->info("Pruned {$staleDeleted} stale pending checkouts and {$completedDeleted} completed pending checkouts.");

        return self::SUCCESS;
    }
}
