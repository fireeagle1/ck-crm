<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'payment_status',
        'fulfilment_status',
        'stripe_checkout_session_id',
        'stripe_payment_intent_id',
        'total_amount',
        'admin_notes',
        'fulfilled_at',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'fulfilled_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'company_id', 'company_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
