<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->enum('type', ['checkout', 'return']);
            $table->json('photos')->default('[]');
            $table->text('condition_notes')->nullable();
            $table->boolean('damage_flagged')->default(false);
            $table->foreignId('inspected_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('inspected_at');
            $table->timestamps();

            $table->index('booking_id', 'idx_inspections_booking');
            $table->index(['booking_id', 'type'], 'idx_inspections_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_inspections');
    }
};
