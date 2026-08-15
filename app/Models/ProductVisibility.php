<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProductVisibility extends Model
{
    use HasFactory;

    protected $table = 'product_visibilities';

    protected $fillable = [
        'product_id',
        'visibility_type',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function customers(): BelongsToMany
    {
        return $this->belongsToMany(
            Customer::class,
            'product_visibility_customers',
            'product_visibility_id',
            'company_id',
            'id',
            'company_id'
        );
    }

    public function tiers(): BelongsToMany
    {
        return $this->belongsToMany(
            CustomerTier::class,
            'product_visibility_tiers',
            'product_visibility_id',
            'customer_tier_id'
        );
    }
}
