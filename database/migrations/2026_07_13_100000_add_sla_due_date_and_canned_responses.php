<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add first_replied_at and due_at to tickets
        Schema::table('tickets', function (Blueprint $table) {
            $table->timestamp('first_replied_at')->nullable()->after('attachment_path');
            $table->timestamp('due_at')->nullable()->after('first_replied_at');
        });

        // Canned responses table for quick reply templates
        Schema::create('canned_responses', function (Blueprint $table) {
            $table->id();
            $table->string('title');        // Short name shown in dropdown
            $table->text('body');           // Full response text
            $table->string('category')->nullable(); // Optional grouping
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('canned_responses');

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['first_replied_at', 'due_at']);
        });
    }
};
