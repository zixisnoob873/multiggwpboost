<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('contact_messages')->where('status', 'open')->update(['status' => 'read']);
        DB::table('contact_messages')->where('status', 'closed')->update(['status' => 'replied']);
        DB::table('contact_messages')->where('status', 'spam')->update(['status' => 'ignored']);
    }

    public function down(): void
    {
        DB::table('contact_messages')->where('status', 'read')->update(['status' => 'open']);
        DB::table('contact_messages')->where('status', 'replied')->update(['status' => 'closed']);
        DB::table('contact_messages')->where('status', 'ignored')->update(['status' => 'spam']);
    }
};
