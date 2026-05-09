<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('booster_payout_rate', 5, 2)->default(60)->after('price_cents');
            $table->bigInteger('booster_payout_cents')->default(0)->after('booster_payout_rate');
        });

        $percentage = (float) env('BOOSTER_PAYOUT_PERCENTAGE', 60);
        $multiplier = max(0, $percentage / 100);

        DB::table('orders')
            ->whereNull('booster_payout_cents')
            ->orWhere('booster_payout_cents', 0)
            ->update([
                'booster_payout_rate' => $percentage,
                'booster_payout_cents' => DB::raw('ROUND(price_cents * '.$multiplier.')'),
            ]);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['booster_payout_rate', 'booster_payout_cents']);
        });
    }
};
