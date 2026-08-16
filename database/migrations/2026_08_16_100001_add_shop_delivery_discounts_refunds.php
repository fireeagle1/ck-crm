<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add delivery_charge to products (static per-product shipping cost)
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('delivery_charge', 8, 2)->nullable()->after('delivery_instructions');
        });

        // 2. Add delivery_method and delivery_charge to orders
        Schema::table('orders', function (Blueprint $table) {
            $table->string('delivery_method', 20)->nullable()->after('delivery_country'); // 'delivery' or 'collection'
            $table->decimal('delivery_charge', 8, 2)->default(0)->after('delivery_method');
            $table->string('discount_code', 50)->nullable()->after('delivery_charge');
            $table->decimal('discount_amount', 8, 2)->default(0)->after('discount_code');
            $table->decimal('refund_amount', 8, 2)->default(0)->after('discount_amount');
            $table->string('refund_status', 20)->nullable()->after('refund_amount'); // null, 'partial', 'full'
            $table->string('stripe_refund_id')->nullable()->after('refund_status');
        });

        // 3. Create discount_codes table
        Schema::create('discount_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('type', 20); // 'percentage' or 'fixed'
            $table->decimal('value', 8, 2); // percentage (e.g. 10.00 = 10%) or fixed amount in £
            $table->decimal('min_order_amount', 8, 2)->nullable(); // minimum order total to apply
            $table->decimal('max_discount_amount', 8, 2)->nullable(); // cap for percentage discounts
            $table->integer('usage_limit')->nullable(); // null = unlimited
            $table->integer('times_used')->default(0);
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('delivery_charge');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'delivery_method',
                'delivery_charge',
                'discount_code',
                'discount_amount',
                'refund_amount',
                'refund_status',
                'stripe_refund_id',
            ]);
        });

        Schema::dropIfExists('discount_codes');
    }
};
