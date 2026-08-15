<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_visibilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->enum('visibility_type', ['all', 'customers', 'tiers'])->default('all');
            $table->timestamps();
        });

        Schema::create('product_visibility_customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_visibility_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('company_id');
            $table->foreign('company_id')->references('company_id')->on('customers')->cascadeOnDelete();
            $table->unique(['product_visibility_id', 'company_id']);
        });

        Schema::create('product_visibility_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_visibility_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_tier_id')->constrained()->cascadeOnDelete();
            $table->unique(['product_visibility_id', 'customer_tier_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_visibility_tiers');
        Schema::dropIfExists('product_visibility_customers');
        Schema::dropIfExists('product_visibilities');
    }
};
