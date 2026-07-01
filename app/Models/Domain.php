<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Domain extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'domain_name',
        'cost',
        'registrar',
        'registration_date',
        'expiry_date',
        'domain_admin_notes',
        'enom_response',
    ];

    protected $casts = [
        'registration_date' => 'date',
        'expiry_date' => 'date',
        'cost' => 'decimal:2',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'company_id', 'company_id');
    }
}
