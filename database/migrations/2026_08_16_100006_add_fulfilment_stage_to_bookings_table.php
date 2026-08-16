<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('fulfilment_stage', 20)->default('ordered')->after('status');
            $table->index('fulfilment_stage', 'idx_bookings_fulfilment_stage');
        });

        // Backfill existing bookings based on current status
        DB::table('bookings')->where('status', 'confirmed')->update(['fulfilment_stage' => 'ordered']);
        DB::table('bookings')->where('status', 'active')->update(['fulfilment_stage' => 'checked_out']);
        DB::table('bookings')->where('status', 'returned')->update(['fulfilment_stage' => 'inspected']);
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex('idx_bookings_fulfilment_stage');
            $table->dropColumn('fulfilment_stage');
        });
    }
};
