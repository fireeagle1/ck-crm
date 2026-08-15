<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_tiers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('customer_tier_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->foreignId('customer_tier_id')->constrained()->cascadeOnDelete();
            $table->foreign('company_id')->references('company_id')->on('customers')->cascadeOnDelete();
            $table->unique(['company_id', 'customer_tier_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_tier_assignments');
        Schema::dropIfExists('customer_tiers');
    }
};
