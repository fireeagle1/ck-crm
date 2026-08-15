<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CustomerTier extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
    ];

    public function customers(): BelongsToMany
    {
        return $this->belongsToMany(
            Customer::class,
            'customer_tier_assignments',
            'customer_tier_id',
            'company_id',
            'id',
            'company_id'
        );
    }

    public function visibilities(): BelongsToMany
    {
        return $this->belongsToMany(
            ProductVisibility::class,
            'product_visibility_tiers',
            'customer_tier_id',
            'product_visibility_id'
        );
    }
}
