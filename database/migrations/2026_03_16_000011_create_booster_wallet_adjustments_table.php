<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booster_wallet_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booster_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('admin_id')->constrained('users')->cascadeOnDelete();
            $table->bigInteger('amount_cents');
            $table->text('reason');
            $table->timestamps();

            $table->index(['booster_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booster_wallet_adjustments');
    }
};
