<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('game_characters')) {
            return;
        }

        Schema::create('game_characters', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->string('slug', 140);
            $table->string('name');
            $table->string('role')->nullable();
            $table->foreignId('portrait_asset_id')->nullable()->constrained('game_assets')->nullOnDelete();
            $table->string('source_id')->nullable();
            $table->string('source_type', 80)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['game_id', 'slug']);
            $table->index(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_characters');
    }
};
