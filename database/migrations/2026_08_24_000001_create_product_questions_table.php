<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('product_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->string('input_type'); // free_text, textarea, date, email, phone, select, number
            $table->json('options')->nullable(); // JSON array of choices for "select" type
            $table->boolean('is_required')->default(false);
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();

            $table->index(['product_id', 'display_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_questions');
    }
};
