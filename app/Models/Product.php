<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'product_type',
        'price',
        'billing_frequency',
        'stock_quantity',
        'image_path',
        'is_archived',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'stock_quantity' => 'integer',
        'is_archived' => 'boolean',
    ];

    public function visibilityRule(): HasOne
    {
        return $this->hasOne(ProductVisibility::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Check if the product is available for purchase.
     * Available means not archived AND (stock is unlimited [null] OR stock > 0).
     */
    public function isAvailable(): bool
    {
        return !$this->is_archived && ($this->stock_quantity === null || $this->stock_quantity > 0);
    }

    /**
     * Scope to filter products visible to a specific customer based on visibility rules.
     */
    public function scopeVisible(Builder $query, Customer $customer): Builder
    {
        return $query->where('is_archived', false)->where(function ($q) use ($customer) {
            $q->whereDoesntHave('visibilityRule')
              ->orWhereHas('visibilityRule', function ($vr) use ($customer) {
                  $vr->where('visibility_type', 'all')
                     ->orWhere(function ($inner) use ($customer) {
                         $inner->where('visibility_type', 'customers')
                               ->whereHas('customers', fn ($c) => $c->where('customers.company_id', $customer->company_id));
                     })
                     ->orWhere(function ($inner) use ($customer) {
                         $inner->where('visibility_type', 'tiers')
                               ->whereHas('tiers', fn ($t) => $t->whereIn(
                                   'customer_tier_id',
                                   $customer->tiers()->pluck('customer_tiers.id')
                               ));
                     });
              });
        });
    }

    /**
     * Scope to exclude archived products.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_archived', false);
    }
}
