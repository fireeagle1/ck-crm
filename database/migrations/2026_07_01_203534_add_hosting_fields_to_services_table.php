<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->string('service_type')->nullable()->after('service_short'); // Web Hosting, Technical Support, Domain Registration, Other
            $table->string('domain_name')->nullable()->after('service_type');
            $table->string('cpanel_username')->nullable()->after('domain_name');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn(['service_type', 'domain_name', 'cpanel_username']);
        });
    }
};
