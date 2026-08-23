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
        Schema::create('question_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_question_id')->nullable()->constrained()->nullOnDelete();
            $table->text('answer_value')->nullable();
            $table->string('question_label'); // Snapshot of label at time of answer
            $table->string('question_type');  // Snapshot of input_type at time of answer
            $table->timestamps();

            $table->index('order_item_id');
            $table->index('product_question_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('question_answers');
    }
};
