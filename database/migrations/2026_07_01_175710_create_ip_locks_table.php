<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ip_locks', function (Blueprint $table) {
            $table->id();
            $table->string('ip_address')->unique();
            $table->integer('failed_attempts')->default(0);
            $table->boolean('is_locked')->default(false);
            $table->timestamp('lock_until')->nullable();
            $table->timestamp('last_failed_login')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ip_locks');
    }
};
