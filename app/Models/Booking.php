<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_item_id',
        'product_id',
        'company_id',
        'start_date',
        'end_date',
        'quantity',
        'total_price',
        'status',
        'block_reason',
        'fulfilment_stage',
        'returned_at',
        'overdue_notified_at',
        'signature_data',
        'agreement_accepted_at',
        'agreement_text_snapshot',
        'confirmation_pdf_path',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'quantity' => 'integer',
        'total_price' => 'decimal:2',
        'returned_at' => 'datetime',
        'overdue_notified_at' => 'datetime',
        'agreement_accepted_at' => 'datetime',
        'fulfilment_stage' => 'string',
    ];

    // ──────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'company_id', 'company_id');
    }

    public function assignedAssets(): HasMany
    {
        return $this->hasMany(BookingAsset::class);
    }

    public function inspections(): HasMany
    {
        return $this->hasMany(BookingInspection::class);
    }

    public function checkoutInspection(): HasOne
    {
        return $this->hasOne(BookingInspection::class)->where('type', 'checkout');
    }

    public function returnInspection(): HasOne
    {
        return $this->hasOne(BookingInspection::class)->where('type', 'return');
    }

    // ──────────────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────────────

    /**
     * Scope to filter bookings with status 'confirmed'.
     */
    public function scopeConfirmed(Builder $query): Builder
    {
        return $query->where('status', 'confirmed');
    }

    /**
     * Scope to filter bookings with status 'active'.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to filter upcoming bookings (start_date is in the future).
     */
    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('start_date', '>', now()->toDateString());
    }

    /**
     * Scope to filter bookings for a specific product.
     */
    public function scopeForProduct(Builder $query, int $productId): Builder
    {
        return $query->where('product_id', $productId);
    }

    /**
     * Scope to find bookings whose date range overlaps the given range.
     * Two ranges [A, B] and [C, D] overlap when A <= D AND C <= B.
     */
    public function scopeOverlapping(Builder $query, $startDate, $endDate): Builder
    {
        return $query->where('start_date', '<=', $endDate)
                     ->where('end_date', '>=', $startDate);
    }

    /**
     * Scope to filter bookings at a specific fulfilment stage.
     */
    public function scopeAtStage(Builder $query, string $stage): Builder
    {
        return $query->where('fulfilment_stage', $stage);
    }

    /**
     * Scope to filter bookings that still need action (all stages before 'inspected').
     */
    public function scopeNeedsAction(Builder $query): Builder
    {
        return $query->whereIn('fulfilment_stage', [
            'ordered',
            'packing',
            'ready',
            'checked_out',
            'returned',
        ]);
    }
}
