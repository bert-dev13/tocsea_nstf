<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    public const ROLE_ADMIN = 'admin';
    public const ROLE_USER = 'user';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'name',
        'email',
        'password',
        'province',
        'municipality',
        'barangay',
        'last_login_at',
        'is_admin',
        'role',
        'is_disabled',
        'status',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'is_disabled' => 'boolean',
        ];
    }

    /**
     * Check if the user has full admin privileges.
     */
    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN || (bool) $this->is_admin;
    }

    /**
     * Check if the user account is active.
     */
    public function isActive(): bool
    {
        return !$this->is_disabled;
    }

    /**
     * Get status attribute (active/inactive) from is_disabled.
     */
    public function getStatusAttribute(): string
    {
        return (bool) ($this->attributes['is_disabled'] ?? false) ? self::STATUS_INACTIVE : self::STATUS_ACTIVE;
    }

    /**
     * Set status attribute (updates is_disabled).
     */
    public function setStatusAttribute(string $value): void
    {
        $this->attributes['is_disabled'] = $value === self::STATUS_INACTIVE;
    }

    /**
     * Get human-readable role label.
     */
    public function getRoleLabelAttribute(): string
    {
        return $this->role === self::ROLE_ADMIN ? 'Admin' : 'User';
    }

    /**
     * Get human-readable status label.
     */
    public function getStatusLabelAttribute(): string
    {
        return $this->status === self::STATUS_ACTIVE ? 'Active' : 'Inactive';
    }

    public function soilLossRecords()
    {
        return $this->hasMany(SoilLossRecord::class);
    }

    public function regressionModels()
    {
        return $this->hasMany(RegressionModel::class);
    }

    public function notifications()
    {
        return $this->hasMany(UserNotification::class);
    }

    public function calculationHistories()
    {
        return $this->hasMany(CalculationHistory::class);
    }
}
