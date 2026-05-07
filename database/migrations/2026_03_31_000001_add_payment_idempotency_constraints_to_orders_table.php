<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Services\Payments\OrderPaymentIdentifierRepairService;

return new class extends Migration
{
    public function up(): void
    {
        // Repair legacy duplicate identifiers first so the unique constraints
        // preserve one canonical financial record and annotate duplicates instead
        // of deleting paid order history.
        app(OrderPaymentIdentifierRepairService::class)->repair(false);

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_stripe_session_id_index');
            $table->unique('stripe_session_id');
            $table->unique('payment_reference');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique('orders_stripe_session_id_unique');
            $table->dropUnique('orders_payment_reference_unique');
            $table->index('stripe_session_id');
        });
    }
};
