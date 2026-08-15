<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('delivery_address_line1')->nullable()->after('admin_notes');
            $table->string('delivery_address_line2')->nullable()->after('delivery_address_line1');
            $table->string('delivery_city')->nullable()->after('delivery_address_line2');
            $table->string('delivery_state')->nullable()->after('delivery_city');
            $table->string('delivery_postal_code')->nullable()->after('delivery_state');
            $table->string('delivery_country')->nullable()->after('delivery_postal_code');
            $table->string('invoice_pdf_path')->nullable()->after('delivery_country');
        });

        // Modify payment_status enum to include 'paid_offline'
        // SQLite doesn't support MODIFY COLUMN; enum is enforced at application level for SQLite
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE orders MODIFY COLUMN payment_status ENUM('pending', 'paid', 'failed', 'paid_offline') DEFAULT 'pending'");
        }
    }

    public function down(): void
    {
        // Revert payment_status enum
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE orders MODIFY COLUMN payment_status ENUM('pending', 'paid', 'failed') DEFAULT 'pending'");
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'delivery_address_line1',
                'delivery_address_line2',
                'delivery_city',
                'delivery_state',
                'delivery_postal_code',
                'delivery_country',
                'invoice_pdf_path',
            ]);
        });
    }
};
