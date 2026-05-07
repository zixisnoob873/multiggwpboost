<?php

use App\Services\Finance\WithdrawalRequestReconciliationService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('withdrawal_requests', function (Blueprint $table) {
            $table->string('reconciliation_status')->nullable()->after('status');
            $table->timestamp('reconciled_at')->nullable()->after('processed_at');
            $table->index('reconciliation_status');
        });

        app(WithdrawalRequestReconciliationService::class)->reconcile(false);
    }

    public function down(): void
    {
        Schema::table('withdrawal_requests', function (Blueprint $table) {
            $table->dropIndex(['reconciliation_status']);
            $table->dropColumn(['reconciliation_status', 'reconciled_at']);
        });
    }
};
