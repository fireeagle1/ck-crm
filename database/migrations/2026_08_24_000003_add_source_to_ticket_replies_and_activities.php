<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Additive, nullable column recording the origin of a write ('admin' or 'mcp').
        // Nullable with no default so existing admin writes and historical rows are unaffected.
        Schema::table('ticket_replies', function (Blueprint $table) {
            $table->string('source')->nullable()->after('user_id'); // 'admin' | 'mcp'
        });

        Schema::table('ticket_activities', function (Blueprint $table) {
            $table->string('source')->nullable()->after('user_id'); // 'admin' | 'mcp'
        });
    }

    public function down(): void
    {
        Schema::table('ticket_replies', function (Blueprint $table) {
            $table->dropColumn('source');
        });

        Schema::table('ticket_activities', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
