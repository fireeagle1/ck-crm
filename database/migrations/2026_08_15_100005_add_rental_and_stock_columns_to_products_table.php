<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->integer('min_rental_days')->nullable()->after('stock_quantity');
            $table->integer('cooldown_days')->nullable()->default(0)->after('min_rental_days');
            $table->longText('rental_agreement_text')->nullable()->after('cooldown_days');
            $table->text('delivery_instructions')->nullable()->after('rental_agreement_text');
            $table->integer('low_stock_threshold')->nullable()->after('delivery_instructions');
            $table->boolean('low_stock_notified')->default(false)->after('low_stock_threshold');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'min_rental_days',
                'cooldown_days',
                'rental_agreement_text',
                'delivery_instructions',
                'low_stock_threshold',
                'low_stock_notified',
            ]);
        });
    }
};
