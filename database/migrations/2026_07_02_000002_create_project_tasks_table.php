<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->string('status', 50)->default('To Do');
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();

            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            $table->index(['project_id', 'display_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_tasks');
    }
};
