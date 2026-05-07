<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booster_applications', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('current_rank');
            $table->string('peak_rank');
            $table->string('average_time');
            $table->string('discord');
            $table->string('main_account_tracker', 2048);
            $table->string('marketplace_profile', 2048)->nullable();
            $table->json('regions');
            $table->string('status')->default('new');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booster_applications');
    }
};
