<?php

use App\Services\DataCleanup\UniqueIndexPreflight;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M4: unique indexes on services.cpanel_username and services.domain_name.
 * The preflight throws (logging every duplicate) before any index is created.
 */
return new class extends Migration
{
    public function up(): void
    {
        (new UniqueIndexPreflight)->assertNoDuplicates();

        foreach (UniqueIndexPreflight::COLUMNS as $column) {
            if (! Schema::hasIndex('services', [$column], 'unique')) {
                Schema::table('services', function (Blueprint $table) use ($column) {
                    $table->unique($column, "services_{$column}_unique");
                });
            }
        }
    }

    public function down(): void
    {
        foreach (UniqueIndexPreflight::COLUMNS as $column) {
            if (Schema::hasIndex('services', "services_{$column}_unique")) {
                Schema::table('services', function (Blueprint $table) use ($column) {
                    $table->dropUnique("services_{$column}_unique");
                });
            }
        }
    }
};
