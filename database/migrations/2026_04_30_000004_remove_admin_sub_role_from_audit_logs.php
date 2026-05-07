<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('admin_audit_logs') || ! Schema::hasColumn('admin_audit_logs', 'actor_admin_role')) {
            return;
        }

        Schema::table('admin_audit_logs', function (Blueprint $table): void {
            $table->dropColumn('actor_admin_role');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('admin_audit_logs') || Schema::hasColumn('admin_audit_logs', 'actor_admin_role')) {
            return;
        }

        Schema::table('admin_audit_logs', function (Blueprint $table): void {
            $table->string('actor_admin_role', 50)->nullable();
        });
    }
};
