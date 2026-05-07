<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booster_wallet_adjustments', function (Blueprint $table) {
            $table->string('type')->default('deduct')->after('admin_id');
        });
    }

    public function down(): void
    {
        Schema::table('booster_wallet_adjustments', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
