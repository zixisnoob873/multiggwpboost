<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_chat_thread_id')->constrained('order_chat_threads')->cascadeOnDelete();
            $table->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('sender_role', 32);
            $table->string('sender_name')->nullable();
            $table->text('body');
            $table->timestamps();

            $table->index(['order_chat_thread_id', 'id']);
            $table->index(['order_chat_thread_id', 'created_at']);
            $table->index('sender_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_chat_messages');
    }
};
