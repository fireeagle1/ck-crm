<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cmdb', function (Blueprint $table) {
            $table->unsignedBigInteger('product_id')->nullable()->after('customer_id');
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
            $table->index('product_id', 'idx_cmdb_product_id');
        });
    }

    public function down(): void
    {
        Schema::table('cmdb', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->dropIndex('idx_cmdb_product_id');
            $table->dropColumn('product_id');
        });
    }
};
