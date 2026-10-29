<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecurityFinding extends Model
{
    use HasFactory;

    public const STATUS_OPEN = 'open';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_DISMISSED = 'dismissed';

    public const STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_RESOLVED,
        self::STATUS_DISMISSED,
    ];

    protected $fillable = [
        'customer_app_id',
        'severity',
        'description',
        'status',
        'synced_at',
    ];

    protected $casts = [
        'synced_at' => 'datetime',
    ];

    public function customerApp(): BelongsTo
    {
        return $this->belongsTo(CustomerApp::class);
    }
}
