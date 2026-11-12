<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class CustomerApp extends Model
{
    use HasFactory;

    protected $fillable = [
        'service_id',
        'domain_id',
        'display_name',
        'install_url',
        'repo_url',
        'hosting_location',
        'codebase_info',
    ];

    protected static function booted(): void
    {
        static::creating(function (CustomerApp $app): void {
            if (empty($app->public_id)) {
                $app->public_id = strtolower((string) Str::ulid());
            }
        });
    }

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'service_id', 'service_id');
    }

    public function packages(): HasMany
    {
        return $this->hasMany(AppPackage::class);
    }

    public function findings(): HasMany
    {
        return $this->hasMany(SecurityFinding::class);
    }

    public function openFindings(): HasMany
    {
        return $this->findings()->where('status', SecurityFinding::STATUS_OPEN);
    }
}
