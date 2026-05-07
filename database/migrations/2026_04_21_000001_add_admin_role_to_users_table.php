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
                $table->string('admin_role', 40)->nullable()->after('role');
            });
        }

        DB::table('users')
            ->where('role', 'admin')
            ->update(['admin_role' => User::ROLE_SUPER_ADMIN]);
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
