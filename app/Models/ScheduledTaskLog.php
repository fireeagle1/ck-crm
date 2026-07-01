<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScheduledTaskLog extends Model
{
    protected $fillable = [
        'task_name',
        'status',
        'output',
        'meta',
        'started_at',
        'completed_at',
        'duration_seconds',
    ];

    protected $casts = [
        'meta' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Start logging a task. Returns the log entry.
     */
    public static function begin(string $taskName, array $meta = []): static
    {
        return static::create([
            'task_name' => $taskName,
            'status' => 'running',
            'meta' => $meta,
            'started_at' => now(),
        ]);
    }

    /**
     * Mark as completed with output and optional extra meta.
     */
    public function complete(string $output = '', array $extraMeta = []): void
    {
        $this->update([
            'status' => 'completed',
            'output' => $output,
            'meta' => array_merge($this->meta ?? [], $extraMeta),
            'completed_at' => now(),
            'duration_seconds' => (int) $this->started_at->diffInSeconds(now()),
        ]);
    }

    /**
     * Mark as failed with error message.
     */
    public function fail(string $error, array $extraMeta = []): void
    {
        $this->update([
            'status' => 'failed',
            'output' => $error,
            'meta' => array_merge($this->meta ?? [], $extraMeta),
            'completed_at' => now(),
            'duration_seconds' => (int) $this->started_at->diffInSeconds(now()),
        ]);
    }

    /**
     * Prune logs older than X days.
     */
    public static function prune(int $days = 30): int
    {
        return static::where('created_at', '<', now()->subDays($days))->delete();
    }
}
