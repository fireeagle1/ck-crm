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
        'provisioning_status',
        'provisioning_error',
        'provisioning_updated_at',
        'domain_id',
        'order_item_id',
        'whm_package',
        'billing_state',
        'billing_error',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'next_payment_date' => 'date',
        'provisioning_updated_at' => 'datetime',
        'service_monthly_charge' => 'decimal:2',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'company_id', 'company_id');
    }

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class, 'domain_id');
    }

    /** The linked Domain's name when domain_id is set, else the stored column. */
    public function domainName(): ?string
    {
        if ($this->domain_id !== null) {
            $name = $this->domain()->withTrashed()->value('domain_name');
            if ($name !== null) {
                return $name;
            }
        }

        return $this->domain_name;
    }

    public function customerApp(): HasOne
    {
        return $this->hasOne(CustomerApp::class, 'service_id', 'service_id');
    }
}
