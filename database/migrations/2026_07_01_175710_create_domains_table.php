<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('domains', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('domain_name')->unique();
            $table->decimal('cost', 10, 2)->nullable();
            $table->string('registrar')->nullable();
            $table->date('registration_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->text('domain_admin_notes')->nullable();
            $table->text('enom_response')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('company_id')->on('customers')->nullOnDelete();
            $table->index(['company_id', 'expiry_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domains');
    }
};
