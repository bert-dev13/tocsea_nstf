<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegressionModel extends Model
{
    use HasFactory;

    protected $table = 'regression_models';

    protected $fillable = [
        'user_id',
        'name',
        'type',
        'equation_params',
        'location',
        'notes',
        'is_default',
    ];

    protected $casts = [
        'equation_params' => 'array',
        'is_default' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
