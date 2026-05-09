<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'admin_role')) {
                $table->string('admin_role', 32)->nullable()->after('role');
                $table->index(['role', 'admin_role']);
            }
        });

        DB::table('users')
            ->whereIn('role', ['admin', 'manager'])
            ->whereNull('admin_role')
            ->update(['admin_role' => 'super_admin']);

        Schema::table('booster_applications', function (Blueprint $table) {
            if (! Schema::hasColumn('booster_applications', 'admin_notes')) {
                $table->text('admin_notes')->nullable()->after('status');
            }
            if (! Schema::hasColumn('booster_applications', 'reviewed_by')) {
                $table->foreignId('reviewed_by')->nullable()->after('admin_notes')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('booster_applications', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            }
            if (! Schema::hasColumn('booster_applications', 'converted_booster_id')) {
                $table->foreignId('converted_booster_id')->nullable()->after('reviewed_at')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('booster_applications', 'converted_at')) {
                $table->timestamp('converted_at')->nullable()->after('converted_booster_id');
            }
            if (! Schema::hasIndex('booster_applications', ['status', 'created_at'])) {
                $table->index(['status', 'created_at']);
            }
        });

        Schema::table('contact_messages', function (Blueprint $table) {
            if (! Schema::hasColumn('contact_messages', 'assigned_admin_id')) {
                $table->foreignId('assigned_admin_id')->nullable()->after('status')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('contact_messages', 'related_order_id')) {
                $table->foreignId('related_order_id')->nullable()->after('assigned_admin_id')->constrained('orders')->nullOnDelete();
            }
            if (! Schema::hasColumn('contact_messages', 'related_customer_id')) {
                $table->foreignId('related_customer_id')->nullable()->after('related_order_id')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('contact_messages', 'internal_notes')) {
                $table->text('internal_notes')->nullable()->after('related_customer_id');
            }
            if (! Schema::hasColumn('contact_messages', 'closed_at')) {
                $table->timestamp('closed_at')->nullable()->after('internal_notes');
            }
            if (! Schema::hasIndex('contact_messages', ['status', 'created_at'])) {
                $table->index(['status', 'created_at']);
            }
            if (! Schema::hasIndex('contact_messages', ['assigned_admin_id', 'status'])) {
                $table->index(['assigned_admin_id', 'status']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('contact_messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_admin_id');
            $table->dropConstrainedForeignId('related_order_id');
            $table->dropConstrainedForeignId('related_customer_id');
            $table->dropIndex(['status', 'created_at']);
            $table->dropIndex(['assigned_admin_id', 'status']);
            $table->dropColumn(['internal_notes', 'closed_at']);
        });

        Schema::table('booster_applications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropConstrainedForeignId('converted_booster_id');
            $table->dropIndex(['status', 'created_at']);
            $table->dropColumn(['admin_notes', 'reviewed_at', 'converted_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role', 'admin_role']);
            $table->dropColumn('admin_role');
        });
    }
};
