<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pending_checkouts', function (Blueprint $table) {
            $table->id();
            $table->string('token')->unique();
            $table->string('reference')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('payment_method');
            $table->bigInteger('price_cents')->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->foreignId('promo_code_id')->nullable()->constrained('promo_codes')->nullOnDelete();
            $table->string('promo_code')->nullable();
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->json('request_data');
            $table->json('order_payload');
            $table->json('metadata')->nullable();
            $table->foreignId('completed_order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['expires_at']);
            $table->index(['completed_order_id']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_checkouts');
    }
};
