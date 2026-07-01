<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduled_task_logs', function (Blueprint $table) {
            $table->id();
            $table->string('task_name');
            $table->string('status'); // running, completed, failed
            $table->text('output')->nullable();
            $table->json('meta')->nullable(); // flexible key-value data
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->timestamps();

            $table->index(['task_name', 'created_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_task_logs');
    }
};
