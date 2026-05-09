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
        if (! Schema::hasColumn('users', 'admin_role')) {
            Schema::table('users', function (Blueprint $table): void {
                // Keep this SQLite-safe by avoiding column positioning clauses.
                $table->string('admin_role', 40)->nullable();
            });
        }

        DB::table('users')
            ->whereIn('role', ['admin', 'manager', User::ROLE_SUPER_ADMIN])
            ->whereNull('admin_role')
            ->update([
                'admin_role' => config('admin.default_admin_role', User::ROLE_SUPER_ADMIN),
            ]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'admin_role')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('admin_role');
            });
        }
    }
};
