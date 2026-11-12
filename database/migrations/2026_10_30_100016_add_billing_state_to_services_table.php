<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('services', 'billing_state')) {
            Schema::table('services', function (Blueprint $table) {
                $table->string('billing_state', 20)->nullable();
            });
        }

        if (! Schema::hasColumn('services', 'billing_error')) {
            Schema::table('services', function (Blueprint $table) {
                $table->text('billing_error')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['billing_error', 'billing_state'] as $column) {
            if (Schema::hasColumn('services', $column)) {
                Schema::table('services', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
