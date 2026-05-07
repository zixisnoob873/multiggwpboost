<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promo_code_addons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promo_code_id')->constrained('promo_codes')->cascadeOnDelete();
            $table->string('addon_slug');
            $table->string('discount_type');
            $table->decimal('discount_value', 10, 2)->default(0);
            $table->timestamps();

            $table->unique(['promo_code_id', 'addon_slug']);
            $table->index(['addon_slug']);
        });

        Schema::table('pending_checkouts', function (Blueprint $table) {
            $table->json('base_order_payload')->nullable()->after('order_payload');
        });
    }

    public function down(): void
    {
        Schema::table('pending_checkouts', function (Blueprint $table) {
            $table->dropColumn('base_order_payload');
        });

        Schema::dropIfExists('promo_code_addons');
    }
};
