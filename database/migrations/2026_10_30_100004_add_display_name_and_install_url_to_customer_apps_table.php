<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_apps', function (Blueprint $table) {
            // Customer-facing "pretty" name shown instead of a bare URL.
            $table->string('display_name')->nullable()->after('service_id');
            // Custom installation / live URL where the app can be reached.
            $table->string('install_url')->nullable()->after('display_name');
        });
    }

    public function down(): void
    {
        Schema::table('customer_apps', function (Blueprint $table) {
            $table->dropColumn(['display_name', 'install_url']);
        });
    }
};
