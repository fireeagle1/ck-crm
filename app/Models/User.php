<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'name',
        'first_name',
        'last_name',
        'email',
        'password',
        'company_id',
        'phone_number',
        'last_login',
        'last_failed_login',
    ];

    /**
     * Attributes that must be set explicitly (not mass-assignable for security).
     * Use $user->is_admin = true, $user->is_locked = true, etc.
     */
    protected $guarded_note = 'is_admin, is_locked, failed_attempts, lock_until are intentionally excluded from $fillable';

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'is_locked' => 'boolean',
            'lock_until' => 'datetime',
            'last_login' => 'datetime',
            'last_failed_login' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'company_id', 'company_id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? ''));
    }

    public function isAdmin(): bool
    {
        return $this->is_admin === true;
    }

    /**
     * Send the password reset notification using our branded template.
     */
    public function sendPasswordResetNotification($token): void
    {
        $url = url(route('password.reset', ['token' => $token, 'email' => $this->email], false));

        \Illuminate\Support\Facades\Mail::send('emails.password-reset', [
            'url' => $url,
        ], function ($message) {
            $message->to($this->email, $this->full_name)
                    ->subject('Reset Your Password — ' . \App\Models\Setting::get('site_name', 'CK Enterprises UK'));
        });
    }
}
