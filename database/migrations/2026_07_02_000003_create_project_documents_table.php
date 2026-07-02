<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->string('label', 255);
            $table->string('document_type', 50);
            $table->string('file_path', 500);
            $table->string('original_filename', 255);
            $table->unsignedBigInteger('file_size');
            $table->unsignedBigInteger('uploaded_by');
            $table->timestamps();

            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            $table->foreign('uploaded_by')->references('id')->on('users');
            $table->index('project_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_documents');
    }
};
