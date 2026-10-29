<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_findings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_app_id');
            $table->string('severity');
            $table->text('description');
            $table->string('status')->default('open');
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->foreign('customer_app_id')->references('id')->on('customer_apps')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_findings');
    }
};
