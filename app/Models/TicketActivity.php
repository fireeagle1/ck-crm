<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketActivity extends Model
{
    protected $fillable = [
        'ticket_id',
        'user_id',
        'type',
        'old_value',
        'new_value',
        'note',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id', 'ticket_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getDescriptionAttribute(): string
    {
        $who = $this->user?->full_name ?? 'System';

        return match ($this->type) {
            'status_changed' => "{$who} changed status from {$this->old_value} to {$this->new_value}",
            'priority_changed' => "{$who} changed priority from {$this->old_value} to {$this->new_value}",
            'type_changed' => "{$who} changed type from {$this->old_value} to {$this->new_value}",
            'created' => "{$who} created this ticket",
            'closed' => "{$who} closed this ticket",
            default => $this->note ?? "{$who} updated this ticket",
        };
    }
}
