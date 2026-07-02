<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_approval_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('project_document_id')->nullable();
            $table->string('type', 50);
            $table->string('status', 50)->default('Pending');
            $table->unsignedBigInteger('responded_by')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            $table->foreign('project_document_id')->references('id')->on('project_documents')->nullOnDelete();
            $table->foreign('responded_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['project_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_approval_requests');
    }
};
