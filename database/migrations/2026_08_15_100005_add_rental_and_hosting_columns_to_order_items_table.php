<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->string('domain_name')->nullable()->after('billing_frequency');
            $table->integer('quantity')->default(1)->after('domain_name');
            $table->date('rental_start_date')->nullable()->after('quantity');
            $table->date('rental_end_date')->nullable()->after('rental_start_date');
            $table->foreignId('booking_id')->nullable()->after('rental_end_date')
                ->constrained('bookings')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['booking_id']);
            $table->dropColumn([
                'domain_name',
                'quantity',
                'rental_start_date',
                'rental_end_date',
                'booking_id',
            ]);
        });
    }
};
