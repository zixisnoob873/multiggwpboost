<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booster_wallet_adjustments', function (Blueprint $table) {
            $table->foreignId('withdrawal_request_id')
                ->nullable()
                ->after('admin_id')
                ->constrained('withdrawal_requests')
                ->nullOnDelete();

            $table->unique('withdrawal_request_id');
        });
    }

    public function down(): void
    {
        Schema::table('booster_wallet_adjustments', function (Blueprint $table) {
            $table->dropUnique('booster_wallet_adjustments_withdrawal_request_id_unique');
            $table->dropConstrainedForeignId('withdrawal_request_id');
        });
    }
};
