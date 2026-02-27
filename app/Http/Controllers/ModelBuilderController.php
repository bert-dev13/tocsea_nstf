<?php

namespace App\Http\Controllers;

use App\Services\RegressionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ModelBuilderController extends Controller
{
    /** Predictor columns (X) for multiple linear regression */
    private const FEATURE_COLUMNS = [
        'Tropical_Storms',
        'Tropical_Depression',
        'Severe_Tropical_Storms',
        'Typhoons',
        'Super_Typhoons',
        'Floods',
        'Storm_Surges',
        'Precipitation_mm',
        'Seawall_m',
        'Vegetation_area_sqm',
        'Coastal_Elevation',
        'Remaining_Land_Area_sqm', // optional
    ];

    /** Target column (y) */
    private const TARGET_COLUMN = 'Soil_loss_sqm';

    public function __construct(
        private RegressionService $regression
    ) {}

    public function index(): \Illuminate\View\View
    {
        return view('model-builder');
    }

    /**
     * Standalone Saved Equations management page (no regression builder or results).
     */
    public function savedEquations(): \Illuminate\View\View
    {
        return view('model-builder-saved-equations');
    }

    /**
     * Run multiple linear regression on submitted table data.
     */
    public function runRegression(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'rows' => ['required', 'array', 'min:10'],
            'rows.*' => ['required', 'array'],
        ]);

        $rows = $validated['rows'];

        // Count rows with valid target (n). We need n > p = 1 + predictors, so max predictors = n - 2.
        $n = 0;
        foreach ($rows as $row) {
            $targetVal = $row[self::TARGET_COLUMN] ?? null;
            if ($targetVal !== '' && $targetVal !== null && is_numeric($targetVal)) {
                $n++;
            }
        }

        $maxPredictors = max(1, $n - 2);
        $usedFeatures = [];

        foreach (self::FEATURE_COLUMNS as $col) {
            if ($col === self::TARGET_COLUMN) {
                continue;
            }
            if (count($usedFeatures) >= $maxPredictors) {
                break;
            }
            $hasValues = false;
            foreach ($rows as $row) {
                $val = $row[$col] ?? null;
                if ($val !== '' && $val !== null && is_numeric($val)) {
                    $hasValues = true;
                    break;
                }
            }
            if ($hasValues) {
                $usedFeatures[] = $col;
            }
        }

        $X = [];
        $y = [];

        foreach ($rows as $i => $row) {
            $targetVal = $row[self::TARGET_COLUMN] ?? null;
            if ($targetVal === '' || $targetVal === null || !is_numeric($targetVal)) {
                throw ValidationException::withMessages([
                    "rows.{$i}." . self::TARGET_COLUMN => ['Target Soil_loss_sqm must be numeric.'],
                ]);
            }

            $xRow = [];
            $hasAllRequired = true;
            foreach ($usedFeatures as $col) {
                $val = $row[$col] ?? null;
                if ($val === '' || $val === null) {
                    if (!in_array($col, ['Remaining_Land_Area_sqm'], true)) {
                        $hasAllRequired = false;
                        break;
                    }
                    $xRow[$col] = 0;
                } else {
                    if (!is_numeric($val)) {
                        throw ValidationException::withMessages([
                            "rows.{$i}.{$col}" => ["{$col} must be numeric."],
                        ]);
                    }
                    $xRow[$col] = (float) $val;
                }
            }

            if (!$hasAllRequired) {
                throw ValidationException::withMessages([
                    "rows.{$i}" => ['All required predictors must have numeric values.'],
                ]);
            }

            $X[] = $xRow;
            $y[] = (float) $targetVal;
        }

        if (count($usedFeatures) < 1) {
            throw ValidationException::withMessages([
                'rows' => ['At least one predictor column must have values.'],
            ]);
        }

        $n = count($y);
        $p = count($usedFeatures) + 1;
        if ($n <= $p) {
            return response()->json([
                'success' => false,
                'message' => 'Need more observations. You have ' . $n . ' rows but ' . count($usedFeatures) . ' predictors. Use fewer predictors or add more data rows (need more rows than predictors).',
            ], 422);
        }

        try {
            $result = $this->regression->run($usedFeatures, $X, $y);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Regression failed: ' . $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'regression' => $result,
        ]);
    }
}
