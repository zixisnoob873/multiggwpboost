<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('featured_boosters', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('region');
            $table->string('platform')->default('PC');
            $table->decimal('success_rate', 5, 2)->default(0);
            $table->unsignedInteger('active_orders')->default(0);
            $table->boolean('is_verified')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('featured_boosters');
    }
};
