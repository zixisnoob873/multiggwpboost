<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_order_email_dispatches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('fingerprint')->unique();
            $table->string('recipient_email');
            $table->string('recipient_name')->nullable();
            $table->string('email_type');
            $table->string('mailable');
            $table->json('payload');
            $table->json('context')->nullable();
            $table->string('status')->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['status', 'updated_at']);
            $table->index(['order_id', 'email_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_order_email_dispatches');
    }
};
