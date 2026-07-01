<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('ticket_type')->default('Incident')->after('priority'); // Incident or Service Request
            $table->string('request_category')->nullable()->after('ticket_type'); // Website Change, Email Setup, Hardware, Software, Network, Other
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['ticket_type', 'request_category']);
        });
    }
};
