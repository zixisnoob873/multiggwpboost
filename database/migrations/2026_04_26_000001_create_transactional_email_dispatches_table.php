<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactional_email_dispatches', function (Blueprint $table) {
            $table->id();
            $table->string('fingerprint')->unique();
            $table->string('recipient_email');
            $table->string('recipient_name')->nullable();
            $table->string('mailable');
            $table->json('payload')->nullable();
            $table->json('context')->nullable();
            $table->string('status')->default('queued');
            $table->timestamp('queued_at')->nullable();
            $table->timestamps();

            $table->index(['mailable', 'recipient_email']);
            $table->index(['status', 'queued_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactional_email_dispatches');
    }
};
