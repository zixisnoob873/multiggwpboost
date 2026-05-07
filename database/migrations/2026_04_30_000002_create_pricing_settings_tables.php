<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pricing_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 80)->unique();
            $table->json('config');
            $table->unsignedInteger('version')->default(1);
            $table->string('checksum', 64);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['key', 'version']);
        });

        Schema::create('pricing_setting_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pricing_setting_id')->constrained('pricing_settings')->cascadeOnDelete();
            $table->string('key', 80);
            $table->string('action', 40);
            $table->unsignedInteger('version');
            $table->string('checksum', 64);
            $table->json('config');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['key', 'version']);
            $table->index(['action', 'created_at']);
            $table->index(['actor_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_setting_revisions');
        Schema::dropIfExists('pricing_settings');
    }
};
