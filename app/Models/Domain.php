<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Domain extends Model
{
    use HasFactory, SoftDeletes;

    /** Fields owned by the eNom sync (an admin edit marks them in admin_edited_fields). */
    public const ENOM_FIELDS = ['expiry_date', 'auto_renew', 'registrar', 'enom_domain_name_id'];

    public const STATUS_ACTIVE = 'active';

    public const STATUS_MISSING_FROM_ENOM = 'missing_from_enom';

    protected $fillable = [
        'company_id',
        'domain_name',
        'cost',
        'registrar',
        'registration_date',
        'expiry_date',
        'auto_renew',
        'stripe_subscription_id',
        'domain_admin_notes',
        'enom_response',
        'status',
        'enom_domain_name_id',
        'last_synced_at',
        'admin_edited_fields',
    ];

    protected $casts = [
        'registration_date' => 'date',
        'expiry_date' => 'date',
        'cost' => 'decimal:2',
        'auto_renew' => 'boolean',
        'last_synced_at' => 'datetime',
        'admin_edited_fields' => 'array',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'company_id', 'company_id');
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class, 'domain_id');
    }

    /**
     * Save, and when the name changed update linked services.domain_name in the same transaction.
     */
    public function save(array $options = []): bool
    {
        if (! $this->exists || ! $this->isDirty('domain_name')) {
            return parent::save($options);
        }

        return DB::transaction(function () use ($options) {
            $saved = parent::save($options);

            if ($saved) {
                $this->services()->update(['domain_name' => $this->domain_name]);
            }

            return $saved;
        });
    }

    public function scopeEnomSourced(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->whereNotNull('enom_domain_name_id')->orWhere('registrar', 'eNom');
        });
    }

    /** Expiring within 30 days (matches the admin index "expiring" filter). */
    public function scopeExpiring(Builder $query): Builder
    {
        return $query->whereDate('expiry_date', '<=', now()->addDays(30))
            ->whereDate('expiry_date', '>=', now());
    }

    /** Expired within the last year (matches the admin index "expired" filter). */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->whereDate('expiry_date', '<', now())
            ->whereDate('expiry_date', '>=', now()->subYear());
    }

    /** Default list: hides domains that expired over a year ago. */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->whereNull('expiry_date')
                ->orWhereDate('expiry_date', '>=', now()->subYear());
        });
    }

    public function scopeUnassigned(Builder $query): Builder
    {
        return $query->whereNull('company_id');
    }

    public function scopeMissingFromEnom(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_MISSING_FROM_ENOM);
    }
}
