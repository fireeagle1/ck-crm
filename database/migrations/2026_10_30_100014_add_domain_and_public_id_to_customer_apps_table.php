<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('customer_apps', 'domain_id')) {
            Schema::table('customer_apps', function (Blueprint $table) {
                $table->foreignId('domain_id')->nullable()->constrained('domains')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('customer_apps', 'public_id')) {
            Schema::table('customer_apps', function (Blueprint $table) {
                $table->char('public_id', 26)->nullable();
            });
        }

        // Backfill existing rows with lowercase ULIDs, in chunks of 200.
        DB::table('customer_apps')
            ->whereNull('public_id')
            ->chunkById(200, function ($apps) {
                foreach ($apps as $app) {
                    DB::table('customer_apps')
                        ->where('id', $app->id)
                        ->update(['public_id' => strtolower((string) Str::ulid())]);
                }
            });

        $hasUnique = collect(Schema::getIndexes('customer_apps'))
            ->contains(fn (array $i) => $i['unique'] && $i['columns'] === ['public_id']);

        if (! $hasUnique) {
            Schema::table('customer_apps', function (Blueprint $table) {
                $table->unique('public_id');
            });
        }
    }

    public function down(): void
    {
        $hasUnique = collect(Schema::getIndexes('customer_apps'))
            ->contains(fn (array $i) => $i['unique'] && $i['columns'] === ['public_id']);

        if ($hasUnique) {
            Schema::table('customer_apps', function (Blueprint $table) {
                $table->dropUnique(['public_id']);
            });
        }

        if (Schema::hasColumn('customer_apps', 'public_id')) {
            Schema::table('customer_apps', function (Blueprint $table) {
                $table->dropColumn('public_id');
            });
        }

        if (Schema::hasColumn('customer_apps', 'domain_id')) {
            Schema::table('customer_apps', function (Blueprint $table) {
                $table->dropConstrainedForeignId('domain_id');
            });
        }
    }
};
