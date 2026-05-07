<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_filter_presets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('module', 80);
            $table->string('name', 80);
            $table->json('filters');
            $table->timestamps();

            $table->unique(['user_id', 'module', 'name']);
            $table->index(['module', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_filter_presets');
    }
};
