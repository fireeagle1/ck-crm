<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    use HasFactory;

    protected $primaryKey = 'invoice_id';

    /**
     * Statuses that should be excluded from financial calculations.
     */
    public const EXCLUDED_STATUSES = ['Void', 'Uncollectible'];

    protected $fillable = [
        'company_id',
        'stripe_invoice_id',
        'stripe_hosted_url',
        'invoice_status',
        'paid_date',
        'invoice_amount',
        'invoice_items',
        'invoice_date',
        'due_date',
        'admin_notes',
        'customer_notes',
        'amount_after_fees',
    ];

    protected $casts = [
        'paid_date' => 'date',
        'invoice_date' => 'date',
        'due_date' => 'date',
        'invoice_amount' => 'decimal:2',
        'amount_after_fees' => 'decimal:2',
        'invoice_items' => 'array',
    ];

    /**
     * Scope to exclude void and uncollectible invoices from queries.
     */
    public function scopeCollectable(Builder $query): Builder
    {
        return $query->whereNotIn('invoice_status', self::EXCLUDED_STATUSES);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'company_id', 'company_id');
    }
}
