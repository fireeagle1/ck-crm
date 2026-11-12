<?php

use App\Services\DataCleanup\DuplicateValuesException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * M5: services.order_item_id (nullable FK, unique) and order_items.stock_reserved_quantity.
 *
 * Backfills are idempotent (they only fill NULLs). The stock backfill covers only
 * legacy one-off items on orders still pending payment, the only ones cleanup can restock.
 */
return new class extends Migration
{
    private const UNIQUE = 'services_order_item_id_unique';

    public function up(): void
    {
        if (! Schema::hasColumn('services', 'order_item_id')) {
            Schema::table('services', function (Blueprint $table) {
                $table->foreignId('order_item_id')->nullable()->constrained('order_items')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('order_items', 'stock_reserved_quantity')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->unsignedInteger('stock_reserved_quantity')->nullable();
            });
        }

        $this->backfillOrderItemIds();
        $this->backfillStockReserved();

        $this->assertNoDuplicateOrderItemIds();

        if (! Schema::hasIndex('services', ['order_item_id'], 'unique')) {
            Schema::table('services', function (Blueprint $table) {
                $table->unique('order_item_id', self::UNIQUE);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('services', self::UNIQUE)) {
            Schema::table('services', function (Blueprint $table) {
                $table->dropUnique(self::UNIQUE);
            });
        }

        if (Schema::hasColumn('services', 'order_item_id')) {
            Schema::table('services', function (Blueprint $table) {
                $table->dropConstrainedForeignId('order_item_id');
            });
        }

        if (Schema::hasColumn('order_items', 'stock_reserved_quantity')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->dropColumn('stock_reserved_quantity');
            });
        }
    }

    /** Link each unlinked service to the lowest-id order item that references it. */
    private function backfillOrderItemIds(): void
    {
        DB::statement(
            'UPDATE services SET order_item_id = ('
            .'SELECT MIN(order_items.id) FROM order_items WHERE order_items.service_id = services.service_id'
            .') WHERE order_item_id IS NULL AND EXISTS ('
            .'SELECT 1 FROM order_items WHERE order_items.service_id = services.service_id)'
        );
    }

    /** Reserve = ordered quantity for stock-tracked products; 0 for unlimited stock. */
    private function backfillStockReserved(): void
    {
        DB::statement(
            'UPDATE order_items SET stock_reserved_quantity = CASE WHEN EXISTS ('
            .'SELECT 1 FROM products WHERE products.id = order_items.product_id AND products.stock_quantity IS NOT NULL'
            .') THEN order_items.quantity ELSE 0 END '
            ."WHERE stock_reserved_quantity IS NULL AND product_type = 'one_off' AND order_id IN ("
            ."SELECT id FROM orders WHERE payment_status = 'pending')"
        );
    }

    private function assertNoDuplicateOrderItemIds(): void
    {
        $values = DB::table('services')
            ->whereNotNull('order_item_id')
            ->groupBy('order_item_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('order_item_id');

        if ($values->isEmpty()) {
            return;
        }

        $duplicates = $values->map(fn ($value) => [
            'table' => 'services',
            'column' => 'order_item_id',
            'value' => (string) $value,
            'ids' => DB::table('services')->where('order_item_id', $value)->orderBy('service_id')
                ->pluck('service_id')->all(),
        ])->all();

        foreach ($duplicates as $duplicate) {
            Log::error('Unique index preflight failed: duplicate value', $duplicate);
        }

        throw new DuplicateValuesException($duplicates);
    }
};
