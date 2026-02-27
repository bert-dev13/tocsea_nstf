<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalculationHistory extends Model
{
    protected $table = 'calculation_histories';

    protected $fillable = [
        'user_id',
        'saved_equation_id',
        'equation_name',
        'formula_snapshot',
        'inputs',
        'result',
        'notes',
    ];

    protected $casts = [
        'inputs' => 'array',
        'result' => 'decimal:4',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function savedEquation(): BelongsTo
    {
        return $this->belongsTo(SavedEquation::class, 'saved_equation_id');
    }

    public function scopeForUser($query, $user)
    {
        return $query->where('user_id', $user->id);
    }
}
