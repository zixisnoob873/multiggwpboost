<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booster_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('order_number')->unique();
            $table->string('product')->default('Rank Boosting');
            $table->string('status')->default('Pending');
            $table->string('payment_status')->default('pending');
            $table->bigInteger('price_cents')->default(0);
            $table->string('currency', 3)->default('USD');
            $table->json('details')->nullable();
            $table->json('metadata')->nullable();
            $table->string('contact_method')->nullable();
            $table->string('whatsapp')->nullable();
            $table->string('discord')->nullable();
            $table->boolean('is_custom')->default(false);
            $table->string('stripe_session_id')->nullable();
            $table->string('payment_reference')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamps();
            $table->index(['status']);
            $table->index(['booster_id']);
            $table->index(['stripe_session_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
