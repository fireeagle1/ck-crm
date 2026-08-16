<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Stripe price/subscription to use when a customer purchases this product
            $table->string('stripe_price_id')->nullable()->after('billing_frequency');
            // WHM package to provision when a hosting product is fulfilled
            $table->string('whm_package')->nullable()->after('stripe_price_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['stripe_price_id', 'whm_package']);
        });
    }
};
