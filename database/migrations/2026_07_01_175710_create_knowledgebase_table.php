<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledgebase', function (Blueprint $table) {
            $table->id('article_id');
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('title');
            $table->text('content')->nullable();
            $table->string('category')->nullable();
            $table->boolean('is_public')->default(false);
            $table->timestamps();

            $table->foreign('company_id')->references('company_id')->on('customers')->nullOnDelete();
            $table->index('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledgebase');
    }
};
