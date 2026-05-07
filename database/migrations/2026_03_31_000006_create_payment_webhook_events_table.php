<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider');
            $table->string('event_id');
            $table->string('event_type')->nullable();
            $table->string('status')->default('processing');
            $table->unsignedInteger('attempts')->default(0);
            $table->json('payload')->nullable();
            $table->foreignId('pending_checkout_id')->nullable()->constrained('pending_checkouts')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'event_id']);
            $table->index(['status', 'updated_at']);
            $table->index(['provider', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_webhook_events');
    }
};
