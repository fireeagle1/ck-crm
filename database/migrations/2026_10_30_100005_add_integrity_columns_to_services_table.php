<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('services', 'provisioning_status')) {
            Schema::table('services', function (Blueprint $table) {
                $table->string('provisioning_status', 20)->nullable();
            });
        }
        if (! Schema::hasColumn('services', 'provisioning_error')) {
            Schema::table('services', function (Blueprint $table) {
                $table->text('provisioning_error')->nullable();
            });
        }
        if (! Schema::hasColumn('services', 'provisioning_updated_at')) {
            Schema::table('services', function (Blueprint $table) {
                $table->timestamp('provisioning_updated_at')->nullable();
            });
        }
        if (! Schema::hasColumn('services', 'domain_id')) {
            Schema::table('services', function (Blueprint $table) {
                $table->foreignId('domain_id')->nullable()->constrained('domains')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        // A later migration (M7b) adds a unique index on domain_id; drop it first
        // so the column can be removed when this migration is rolled back alone.
        if (Schema::hasIndex('services', 'services_domain_id_unique')) {
            Schema::table('services', function (Blueprint $table) {
                $table->dropUnique('services_domain_id_unique');
            });
        }
        if (Schema::hasColumn('services', 'domain_id')) {
            Schema::table('services', function (Blueprint $table) {
                $table->dropConstrainedForeignId('domain_id');
            });
        }
        foreach (['provisioning_updated_at', 'provisioning_error', 'provisioning_status'] as $column) {
            if (Schema::hasColumn('services', $column)) {
                Schema::table('services', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
