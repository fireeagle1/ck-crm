<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingInspection extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'type',
        'photos',
        'condition_notes',
        'damage_flagged',
        'inspected_by',
        'inspected_at',
    ];

    protected $casts = [
        'photos' => 'array',
        'damage_flagged' => 'boolean',
        'inspected_at' => 'datetime',
    ];

    // ──────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspected_by');
    }
}
