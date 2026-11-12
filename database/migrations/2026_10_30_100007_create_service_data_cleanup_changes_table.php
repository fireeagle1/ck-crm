<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('service_data_cleanup_changes')) {
            return;
        }

        Schema::create('service_data_cleanup_changes', function (Blueprint $table) {
            $table->id();
            $table->string('migration');
            $table->string('table_name', 64);
            $table->string('row_id', 64);
            $table->string('column_name', 64);
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['migration', 'table_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_data_cleanup_changes');
    }
};
