<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('access_token_id')->nullable(); // Passport token id reference, never the token value
            $table->string('mcp_identity'); // client name / bot user reference
            $table->string('tool_name')->nullable(); // null when denial happens before tool resolution
            $table->string('scope_used')->nullable(); // scope required/checked
            $table->enum('outcome', ['success', 'denied', 'error']);
            $table->string('reason')->nullable(); // denial/error reason, no sensitive data
            $table->json('context')->nullable(); // non-sensitive metadata only (e.g. ticket_id, filters)
            $table->timestamp('created_at')->useCurrent(); // invocation time

            $table->index('created_at');
            $table->index(['tool_name', 'outcome']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_audit_logs');
    }
};
