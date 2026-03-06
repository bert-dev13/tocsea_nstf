<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavedEquation extends Model
{
    protected $fillable = ['user_id', 'equation_name', 'formula', 'location', 'notes'];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to records belonging to the given user (ensures per-user data isolation).
     */
    public function scopeForUser($query, $user)
    {
        return $query->where('user_id', $user->id);
    }
}
