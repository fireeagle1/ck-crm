<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductQuestion extends Model
{
    protected $fillable = [
        'product_id',
        'label',
        'input_type',
        'options',
        'is_required',
        'display_order',
    ];

    protected $casts = [
        'options' => 'array',
        'is_required' => 'boolean',
        'display_order' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(QuestionAnswer::class);
    }
}
