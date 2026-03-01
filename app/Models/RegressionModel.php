<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class RegressionModel extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'regression_models';

    protected $fillable = [
        'user_id',
        'name',
        'type',
        'equation_params',
        'location',
        'notes',
        'is_default',
        'is_approved',
        'is_official',
        'is_archived',
    ];

    protected $casts = [
        'equation_params' => 'array',
        'is_default' => 'boolean',
        'is_approved' => 'boolean',
        'is_official' => 'boolean',
        'is_archived' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Status for display: Approved, Pending, Archived */
    public function getStatusLabelAttribute(): string
    {
        if ($this->is_archived) {
            return 'Archived';
        }
        return $this->is_approved ? 'Approved' : 'Pending';
    }

    /** Type for display: Official, Default, Custom (priority: official > default > custom) */
    public function getTypeLabelAttribute(): string
    {
        if ($this->is_official) {
            return 'Official';
        }
        if ($this->is_default) {
            return 'Default';
        }
        return 'Custom';
    }

    /** Human-readable formula from equation_params (equation key or built from coefficients). */
    public function getFormulaDisplayAttribute(): string
    {
        $params = $this->equation_params ?? [];
        if (! empty($params['equation'])) {
            return (string) $params['equation'];
        }
        $intercept = $params['intercept'] ?? 0;
        $coefficients = $params['coefficients'] ?? [];
        if (empty($coefficients)) {
            return 'y = ' . $intercept;
        }
        $parts = ['y = ' . $intercept];
        foreach ($coefficients as $name => $coef) {
            $parts[] = ($coef >= 0 ? '+' : '') . $coef . '·' . $name;
        }
        return implode(' ', $parts);
    }

    /** List of predictor names from equation_params. */
    public function getPredictorsListAttribute(): array
    {
        $params = $this->equation_params ?? [];
        $coefficients = $params['coefficients'] ?? [];
        return array_keys($coefficients);
    }
}
