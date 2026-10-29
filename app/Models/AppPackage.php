<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppPackage extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_app_id',
        'name',
        'version',
        'synced_at',
    ];

    protected $casts = [
        'synced_at' => 'datetime',
    ];

    public function customerApp(): BelongsTo
    {
        return $this->belongsTo(CustomerApp::class);
    }
}
