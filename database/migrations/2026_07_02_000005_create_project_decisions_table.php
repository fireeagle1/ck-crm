<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_decisions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->string('category', 50)->nullable();
            $table->date('date_recorded');
            $table->timestamps();

            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            $table->index(['project_id', 'date_recorded']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_decisions');
    }
};
