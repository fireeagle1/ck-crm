<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('domains', 'status')) {
            Schema::table('domains', function (Blueprint $table) {
                $table->string('status', 32)->default('active')->index();
            });
        }

        if (! Schema::hasColumn('domains', 'enom_domain_name_id')) {
            Schema::table('domains', function (Blueprint $table) {
                $table->string('enom_domain_name_id', 32)->nullable()->index();
            });
        }

        if (! Schema::hasColumn('domains', 'last_synced_at')) {
            Schema::table('domains', function (Blueprint $table) {
                $table->timestamp('last_synced_at')->nullable();
            });
        }

        if (! Schema::hasColumn('domains', 'admin_edited_fields')) {
            Schema::table('domains', function (Blueprint $table) {
                $table->json('admin_edited_fields')->nullable();
            });
        }

        if (! Schema::hasColumn('domains', 'deleted_at')) {
            Schema::table('domains', function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('domains', 'deleted_at')) {
            Schema::table('domains', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }

        if (Schema::hasColumn('domains', 'admin_edited_fields')) {
            Schema::table('domains', function (Blueprint $table) {
                $table->dropColumn('admin_edited_fields');
            });
        }

        if (Schema::hasColumn('domains', 'last_synced_at')) {
            Schema::table('domains', function (Blueprint $table) {
                $table->dropColumn('last_synced_at');
            });
        }

        if (Schema::hasColumn('domains', 'enom_domain_name_id')) {
            Schema::table('domains', function (Blueprint $table) {
                $table->dropIndex(['enom_domain_name_id']);
            });
            Schema::table('domains', function (Blueprint $table) {
                $table->dropColumn('enom_domain_name_id');
            });
        }

        if (Schema::hasColumn('domains', 'status')) {
            Schema::table('domains', function (Blueprint $table) {
                $table->dropIndex(['status']);
            });
            Schema::table('domains', function (Blueprint $table) {
                $table->dropColumn('status');
            });
        }
    }
};
