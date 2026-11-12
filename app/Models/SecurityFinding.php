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

    /** Set when the app has never reported findings; never sent by connectors. */
    public const STATUS_NOT_REPORTED = 'not_reported';

    /** All persisted statuses. */
    public const STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_RESOLVED,
        self::STATUS_DISMISSED,
        self::STATUS_NOT_REPORTED,
    ];

    /** Statuses a connector may submit in an ingestion payload. */
    public const PAYLOAD_STATUSES = [
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
        'fingerprint',
        'last_seen_at',
    ];

    protected $casts = [
        'synced_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    /**
     * Stable identity of a finding within an app: sha256 of the trimmed,
     * whitespace-collapsed, lower-cased description (severity is excluded so a
     * severity change updates the row). Mirrors the M9 backfill.
     */
    public static function fingerprintFor(string $description): string
    {
        $normalised = mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', trim($description))));

        return hash('sha256', $normalised);
    }

    public function customerApp(): BelongsTo
    {
        return $this->belongsTo(CustomerApp::class);
    }
}
