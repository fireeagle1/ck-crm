<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Article extends Model
{
    use HasFactory;

    protected $table = 'knowledgebase';
    protected $primaryKey = 'article_id';

    protected $fillable = [
        'company_id',
        'title',
        'content',
        'category',
        'is_public',
    ];

    protected $casts = [
        'is_public' => 'boolean',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'company_id', 'company_id');
    }
}
