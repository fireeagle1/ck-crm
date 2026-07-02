<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use HasFactory;

    protected $primaryKey = 'company_id';

    protected $fillable = [
        'company_name',
        'customer_name',
        'phone_number',
        'address_line1',
        'address_line2',
        'city',
        'state',
        'postal_code',
        'country',
        'stripe_customer_id',
        'banner_image',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'company_id', 'company_id');
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class, 'company_id', 'company_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'company_id', 'company_id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'company_id', 'company_id');
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class, 'customer_id', 'company_id');
    }

    public function domains(): HasMany
    {
        return $this->hasMany(Domain::class, 'company_id', 'company_id');
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class, 'company_id', 'company_id');
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class, 'company_id', 'company_id');
    }
}
