<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('game_assets')) {
            return;
        }

        Schema::create('game_assets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->string('asset_type', 64);
            $table->string('slug', 140);
            $table->string('label')->nullable();
            $table->string('disk')->default('public');
            $table->string('path')->nullable();
            $table->string('source_url', 2048)->nullable();
            $table->string('source_type', 80)->nullable();
            $table->string('source_name')->nullable();
            $table->text('source_license_notes')->nullable();
            $table->string('checksum', 128)->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('alt_text')->nullable();
            $table->timestamp('source_updated_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['game_id', 'asset_type', 'slug'], 'game_assets_unique_asset');
            $table->index(['asset_type', 'source_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_assets');
    }
};
