<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_apps', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('service_id');
            $table->string('repo_url')->nullable();
            $table->string('hosting_location')->nullable();
            $table->text('codebase_info')->nullable();
            $table->timestamps();

            $table->unique('service_id');
            $table->foreign('service_id')->references('service_id')->on('services')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_apps');
    }
};
