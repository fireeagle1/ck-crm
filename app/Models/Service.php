<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Service extends Model
{
    use HasFactory;

    protected $primaryKey = 'service_id';

    protected $fillable = [
        'company_id',
        'service_short',
        'service_type',
        'domain_name',
        'cpanel_username',
        'status',
        'start_date',
        'end_date',
        'service_monthly_charge',
        'service_payment_frequency',
        'next_payment_date',
        'stripe_subscription_id',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'next_payment_date' => 'date',
        'service_monthly_charge' => 'decimal:2',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'company_id', 'company_id');
    }

    public function customerApp(): HasOne
    {
        return $this->hasOne(CustomerApp::class, 'service_id', 'service_id');
    }
}
