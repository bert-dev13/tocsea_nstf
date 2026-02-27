<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SoilLossRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'province',
        'municipality',
        'barangay',
        'year',
        'soil_loss_tonnes_per_ha',
        'risk_level',
        'model_used',
        'parameters',
    ];

    protected $casts = [
        'parameters' => 'array',
        'soil_loss_tonnes_per_ha' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
