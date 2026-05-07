<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discord_notification_dispatches', function (Blueprint $table) {
            $table->id();
            $table->string('fingerprint')->unique();
            $table->string('webhook_config_key');
            $table->string('message_type');
            $table->json('payload');
            $table->json('context')->nullable();
            $table->string('status')->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['status', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discord_notification_dispatches');
    }
};
