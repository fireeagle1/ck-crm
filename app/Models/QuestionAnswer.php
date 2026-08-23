<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionAnswer extends Model
{
    protected $fillable = [
        'order_item_id',
        'product_question_id',
        'answer_value',
        'question_label',
        'question_type',
    ];

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function productQuestion(): BelongsTo
    {
        return $this->belongsTo(ProductQuestion::class);
    }
}
