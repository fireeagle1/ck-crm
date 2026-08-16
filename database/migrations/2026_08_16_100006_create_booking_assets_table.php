<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->unsignedBigInteger('asset_id');
            $table->timestamp('assigned_at')->useCurrent();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();

            $table->foreign('asset_id')->references('device_id')->on('cmdb')->cascadeOnDelete();
            $table->index('booking_id', 'idx_booking_assets_booking');
            $table->index('asset_id', 'idx_booking_assets_asset');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_assets');
    }
};
