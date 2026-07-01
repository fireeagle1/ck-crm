<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id('invoice_id');
            $table->unsignedBigInteger('company_id');
            $table->string('invoice_status')->default('Unpaid');
            $table->date('paid_date')->nullable();
            $table->decimal('invoice_amount', 10, 2)->default(0);
            $table->json('invoice_items')->nullable();
            $table->date('invoice_date')->nullable();
            $table->date('due_date')->nullable();
            $table->text('admin_notes')->nullable();
            $table->text('customer_notes')->nullable();
            $table->decimal('amount_after_fees', 10, 2)->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('company_id')->on('customers')->cascadeOnDelete();
            $table->index(['company_id', 'invoice_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
