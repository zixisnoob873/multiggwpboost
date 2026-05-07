<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        DB::table('users')
            ->whereIn('role', ['admin', 'manager', 'ops_admin', 'content_admin', 'finance_admin'])
            ->update([
                'role' => User::ROLE_SUPER_ADMIN,
            ]);

        if (! Schema::hasColumn('users', 'admin_role')) {
            return;
        }

        $hasAdminRoleIndex = Schema::hasIndex('users', ['role', 'admin_role']);

        Schema::table('users', function (Blueprint $table) use ($hasAdminRoleIndex): void {
            if ($hasAdminRoleIndex) {
                $table->dropIndex(['role', 'admin_role']);
            }

            $table->dropColumn('admin_role');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('users') || Schema::hasColumn('users', 'admin_role')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->string('admin_role', 40)->nullable();
        });
    }
};
