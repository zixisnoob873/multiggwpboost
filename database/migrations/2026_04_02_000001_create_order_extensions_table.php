<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_extensions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->string('service_type');
            $table->string('checkout_reference')->unique();
            $table->bigInteger('amount_cents');
            $table->bigInteger('previous_total_cents');
            $table->bigInteger('new_total_cents');
            $table->bigInteger('previous_booster_payout_cents');
            $table->bigInteger('new_booster_payout_cents');
            $table->json('selection_payload');
            $table->json('previous_order_payload');
            $table->json('updated_order_payload');
            $table->string('payment_provider', 32)->nullable();
            $table->string('payment_reference')->nullable();
            $table->string('stripe_session_id')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'created_at']);
            $table->index(['customer_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_extensions');
    }
};
