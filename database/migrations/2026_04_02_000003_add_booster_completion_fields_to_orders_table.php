<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('completion_proof_path')->nullable()->after('assigned_at');
            $table->timestamp('completion_proof_uploaded_at')->nullable()->after('completion_proof_path');
            $table->timestamp('completed_at')->nullable()->after('completion_proof_uploaded_at');
            $table->foreignId('completed_by_booster_id')
                ->nullable()
                ->after('completed_at')
                ->constrained('users')
                ->nullOnDelete();

            $table->index('completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['completed_at']);
            $table->dropConstrainedForeignId('completed_by_booster_id');
            $table->dropColumn([
                'completion_proof_path',
                'completion_proof_uploaded_at',
                'completed_at',
            ]);
        });
    }
};
