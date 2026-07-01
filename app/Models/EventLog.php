<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventLog extends Model
{
    public $timestamps = false;

    protected $table = 'event_log';

    protected $fillable = [
        'user_id',
        'action',
        'action_code',
        'ip_address',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function log(?int $userId, string $action, int $code, ?string $ip = null): void
    {
        static::create([
            'user_id' => $userId,
            'action' => $action,
            'action_code' => $code,
            'ip_address' => $ip ?? request()->ip(),
        ]);
    }
}
