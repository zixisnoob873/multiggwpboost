<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_tips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('booster_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('recipient_type', 32);
            $table->string('checkout_reference')->unique();
            $table->bigInteger('amount_cents');
            $table->string('payment_provider', 32)->nullable();
            $table->string('payment_reference')->nullable();
            $table->string('stripe_session_id')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['recipient_type', 'created_at']);
            $table->index(['booster_id', 'recipient_type', 'created_at']);
            $table->index(['order_id', 'recipient_type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_tips');
    }
};
