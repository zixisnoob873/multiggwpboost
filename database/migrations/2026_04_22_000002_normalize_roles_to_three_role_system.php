<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users')) {
            DB::table('users')
                ->whereIn('role', ['admin', 'manager'])
                ->update([
                    'role' => User::ROLE_SUPER_ADMIN,
                ]);

            DB::table('users')
                ->where('role', User::ROLE_SUPER_ADMIN)
                ->whereNull('admin_role')
                ->update([
                    'admin_role' => config('admin.default_admin_role', User::ROLE_SUPER_ADMIN),
                ]);

            DB::table('users')
                ->where('role', '!=', User::ROLE_SUPER_ADMIN)
                ->update([
                    'admin_role' => null,
                ]);
        }

        if (Schema::hasTable('order_chat_messages')) {
            DB::table('order_chat_messages')
                ->whereIn('sender_role', ['admin', 'manager'])
                ->update([
                    'sender_role' => User::ROLE_SUPER_ADMIN,
                ]);
        }

        if (Schema::hasTable('order_messages')) {
            DB::table('order_messages')
                ->whereIn('sender_role', ['admin', 'manager'])
                ->update([
                    'sender_role' => User::ROLE_SUPER_ADMIN,
                ]);
        }

        if (Schema::hasTable('admin_audit_logs')) {
            DB::table('admin_audit_logs')
                ->whereIn('actor_role', ['admin', 'manager'])
                ->update([
                    'actor_role' => User::ROLE_SUPER_ADMIN,
                ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users')) {
            DB::table('users')
                ->where('role', User::ROLE_SUPER_ADMIN)
                ->where('admin_role', config('admin.default_admin_role', User::ROLE_SUPER_ADMIN))
                ->update([
                    'role' => 'admin',
                ]);
        }

        if (Schema::hasTable('order_chat_messages')) {
            DB::table('order_chat_messages')
                ->where('sender_role', User::ROLE_SUPER_ADMIN)
                ->update([
                    'sender_role' => 'admin',
                ]);
        }

        if (Schema::hasTable('order_messages')) {
            DB::table('order_messages')
                ->where('sender_role', User::ROLE_SUPER_ADMIN)
                ->update([
                    'sender_role' => 'admin',
                ]);
        }

        if (Schema::hasTable('admin_audit_logs')) {
            DB::table('admin_audit_logs')
                ->where('actor_role', User::ROLE_SUPER_ADMIN)
                ->update([
                    'actor_role' => 'admin',
                ]);
        }
    }
};
