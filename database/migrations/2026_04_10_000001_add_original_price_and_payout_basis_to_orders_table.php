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
            $table->bigInteger('original_price_cents')
                ->nullable()
                ->after('price_cents');
            $table->bigInteger('booster_payout_basis_cents')
                ->nullable()
                ->after('booster_payout_cents');
        });

        $originalPriceExpression = 'CASE WHEN price_cents + ROUND(COALESCE(discount_amount, 0) * 100) > 0 THEN price_cents + ROUND(COALESCE(discount_amount, 0) * 100) ELSE 0 END';

        DB::table('orders')->update([
            'original_price_cents' => DB::raw($originalPriceExpression),
            // Preserve stored payout amounts for legacy orders while adding an explicit basis for new logic.
            'booster_payout_basis_cents' => DB::raw($originalPriceExpression),
        ]);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'original_price_cents',
                'booster_payout_basis_cents',
            ]);
        });
    }
};
