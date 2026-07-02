<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_status_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->string('status', 50);
            $table->unsignedBigInteger('changed_by');
            $table->timestamp('created_at')->nullable();

            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            $table->foreign('changed_by')->references('id')->on('users');
            $table->index(['project_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_status_logs');
    }
};
