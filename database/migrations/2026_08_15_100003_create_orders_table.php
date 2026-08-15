<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->enum('payment_status', ['pending', 'paid', 'failed'])->default('pending');
            $table->enum('fulfilment_status', ['pending', 'awaiting_fulfilment', 'completed'])->default('pending');
            $table->string('stripe_checkout_session_id')->nullable()->unique();
            $table->string('stripe_payment_intent_id')->nullable();
            $table->decimal('total_amount', 10, 2);
            $table->text('admin_notes')->nullable();
            $table->timestamp('fulfilled_at')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('company_id')->on('customers')->cascadeOnDelete();
            $table->index(['company_id', 'fulfilment_status']);
            $table->index('fulfilment_status');
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('service_id')->nullable();
            $table->string('product_name');
            $table->string('product_type');
            $table->decimal('price', 10, 2);
            $table->string('billing_frequency')->nullable();
            $table->string('stripe_subscription_id')->nullable();
            $table->timestamps();

            $table->foreign('service_id')->references('service_id')->on('services')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
