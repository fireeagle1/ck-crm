<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id('service_id');
            $table->unsignedBigInteger('company_id');
            $table->string('service_short');
            $table->string('status')->default('Active');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->decimal('service_monthly_charge', 10, 2)->nullable();
            $table->string('service_payment_frequency')->nullable();
            $table->date('next_payment_date')->nullable();
            $table->string('stripe_subscription_id')->nullable()->unique();
            $table->timestamps();

            $table->foreign('company_id')->references('company_id')->on('customers')->cascadeOnDelete();
            $table->index('company_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
