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
        'stripe_price_id',
        'whm_package',
        'stock_quantity',
        'image_path',
        'is_archived',
        'min_rental_days',
        'cooldown_days',
        'rental_agreement_text',
        'delivery_instructions',
        'delivery_charge',
        'low_stock_threshold',
        'low_stock_notified',
        'track_individual_assets',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'delivery_charge' => 'decimal:2',
        'stock_quantity' => 'integer',
        'is_archived' => 'boolean',
        'min_rental_days' => 'integer',
        'cooldown_days' => 'integer',
        'low_stock_threshold' => 'integer',
        'low_stock_notified' => 'boolean',
        'track_individual_assets' => 'boolean',
    ];

    public function visibilityRule(): HasOne
    {
        return $this->hasOne(ProductVisibility::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'product_id');
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(ProductQuestion::class)->orderBy('display_order');
    }

    /**
     * Get the number of available assets for this product.
     * If track_individual_assets is enabled, counts linked assets with 'Available' status.
     * Otherwise, returns the manual stock_quantity value.
     */
    public function getAvailableAssetCount(): int
    {
        if (!$this->track_individual_assets) {
            return $this->stock_quantity ?? 0;
        }

        return $this->assets()->where('asset_status', 'Available')->count();
    }

    /**
     * Get a query builder for available assets linked to this product.
     * Returns assets with status 'Available'.
     */
    public function getAvailableAssets(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->assets()->where('asset_status', 'Available');
    }

    /**
     * Check if the product is an equipment rental type.
     */
    public function isEquipmentRental(): bool
    {
        return $this->product_type === 'equipment_rental';
    }

    /**
     * Check if the product is a hosting type.
     */
    public function isHosting(): bool
    {
        return $this->product_type === 'hosting';
    }

    /**
     * Check if the product is a one-off type.
     */
    public function isOneOff(): bool
    {
        return $this->product_type === 'one_off';
    }

    /**
     * Check if the product has a rental agreement configured.
     */
    public function hasRentalAgreement(): bool
    {
        return !empty($this->rental_agreement_text);
    }

    /**
     * Check if the product has a delivery charge configured.
     */
    public function hasDeliveryCharge(): bool
    {
        return $this->delivery_charge !== null && (float) $this->delivery_charge > 0;
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
