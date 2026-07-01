<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    use HasFactory;

    protected $primaryKey = 'invoice_id';

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

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'company_id', 'company_id');
    }
}
