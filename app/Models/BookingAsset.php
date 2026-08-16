<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingAsset extends Model
{
    use HasFactory;

    protected $table = 'booking_assets';

    protected $fillable = [
        'booking_id',
        'asset_id',
        'assigned_at',
        'released_at',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'released_at' => 'datetime',
    ];

    // ──────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'asset_id', 'device_id');
    }
}
