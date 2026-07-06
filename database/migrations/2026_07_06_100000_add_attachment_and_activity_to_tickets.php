<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add attachment_path to tickets for the original submission
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('attachment_path')->nullable()->after('description');
        });

        // Activity log for audit trail
        Schema::create('ticket_activities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ticket_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('type'); // status_changed, priority_changed, type_changed, created, closed
            $table->string('old_value')->nullable();
            $table->string('new_value')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->foreign('ticket_id')->references('ticket_id')->on('tickets')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['ticket_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_activities');

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn('attachment_path');
        });
    }
};
