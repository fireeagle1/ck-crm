<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->string('status', 50)->default('Not Started');
            $table->string('previous_status', 50)->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('company_id')->on('customers')->cascadeOnDelete();
            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
