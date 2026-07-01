<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('company_id')->nullable()->after('id');
            $table->string('first_name')->nullable()->after('name');
            $table->string('last_name')->nullable()->after('first_name');
            $table->string('phone_number')->nullable()->after('email');
            $table->boolean('is_admin')->default(false)->after('phone_number');
            $table->integer('failed_attempts')->default(0);
            $table->boolean('is_locked')->default(false);
            $table->timestamp('lock_until')->nullable();
            $table->timestamp('last_login')->nullable();
            $table->timestamp('last_failed_login')->nullable();

            $table->index('company_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'company_id', 'first_name', 'last_name', 'phone_number',
                'is_admin', 'failed_attempts', 'is_locked', 'lock_until',
                'last_login', 'last_failed_login',
            ]);
        });
    }
};
