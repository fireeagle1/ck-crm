<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Widen the orders status enums to cover values the application already writes.
     *
     * Two code paths were storing values the enum did not permit, so MySQL
     * rejected every write with error 1265 ("Data truncated"):
     *
     *   - CleanupAbandonedOrdersCommand sets payment_status = 'expired' and
     *     fulfilment_status = 'cancelled' when auto-cancelling unpaid orders.
     *   - Admin ShopOrderController::cancel() (web + API) sets
     *     fulfilment_status = 'cancelled' and checks for it.
     *
     * 'expired' (payment) marks an order auto-cancelled for non-payment, kept
     * distinct from 'failed' (a declined charge). 'cancelled' (fulfilment)
     * marks an order that will not be fulfilled, whether cancelled by an admin
     * or expired by the cleanup job.
     *
     * SQLite does not support MODIFY COLUMN and enforces enums at the
     * application layer only, so it is skipped — matching the convention in
     * 2026_08_15_100005_add_delivery_and_invoice_columns_to_orders_table.php.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE orders MODIFY COLUMN payment_status ENUM('pending', 'paid', 'failed', 'paid_offline', 'expired') DEFAULT 'pending'");
        DB::statement("ALTER TABLE orders MODIFY COLUMN fulfilment_status ENUM('pending', 'awaiting_fulfilment', 'completed', 'cancelled') DEFAULT 'pending'");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        // Fold the new values back into the nearest prior value so the narrower
        // enum does not truncate existing rows on rollback.
        DB::statement("UPDATE orders SET payment_status = 'failed' WHERE payment_status = 'expired'");
        DB::statement("UPDATE orders SET fulfilment_status = 'completed' WHERE fulfilment_status = 'cancelled'");

        DB::statement("ALTER TABLE orders MODIFY COLUMN payment_status ENUM('pending', 'paid', 'failed', 'paid_offline') DEFAULT 'pending'");
        DB::statement("ALTER TABLE orders MODIFY COLUMN fulfilment_status ENUM('pending', 'awaiting_fulfilment', 'completed') DEFAULT 'pending'");
    }
};
