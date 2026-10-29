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
        Schema::create('app_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_app_id')->constrained('customer_apps')->cascadeOnDelete();
            $table->string('name');
            $table->string('version');
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->index('customer_app_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('app_packages');
    }
};
