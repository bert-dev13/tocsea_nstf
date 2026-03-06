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
        'Tropical_Depression',
        'Tropical_Storms',
        'Severe_Tropical_Storms',
        'Typhoons',
        'Super_Typhoons',
        'Floods',
        'Storm_Surges',
        'Precipitation_mm',
        'Seawall_m',
        'Vegetation_area_sqm',
        'Coastal_Elevation',
    ];

    /** Target column (y) */
    private const TARGET_COLUMN = 'Soil_loss_sqm';

    /** Minimum number of rows with valid target and complete predictor data to run regression. */
    private const MIN_VALID_ROWS = 5;

    public function __construct(
        private RegressionService $regression
    ) {}

    public function index(): \Illuminate\View\View
    {
        return view('user.model-builder.index');
    }

    /**
     * Standalone Saved Equations management page (no regression builder or results).
     */
    public function savedEquations(): \Illuminate\View\View
    {
        return view('user.model-builder-saved-equations.index');
    }

    /**
     * Keep only rows that have valid Soil_loss_sqm and numeric values for all predictor columns.
     * Excludes: missing/blank target, target === 0 (placeholder), any predictor missing/blank,
     * and rows that are entirely zeros (target + all predictors zero).
     */
    private function filterCompleteRows(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $targetVal = $row[self::TARGET_COLUMN] ?? null;
            if ($targetVal === '' || $targetVal === null || ! is_numeric($targetVal)) {
                continue;
            }
            $targetFloat = (float) $targetVal;
            // Treat 0 as invalid placeholder for target (unless you explicitly allow it)
            if ($targetFloat === 0.0) {
                continue;
            }
            $complete = true;
            foreach (self::FEATURE_COLUMNS as $col) {
                $val = $row[$col] ?? null;
                if ($val === '' || $val === null || ! is_numeric($val)) {
                    $complete = false;
                    break;
                }
            }
            if (! $complete) {
                continue;
            }
            $out[] = $row;
        }

        return $this->removeAllZeroRows($out);
    }

    /**
     * Remove rows where target and all predictor columns are zero (e.g. blank row #10).
     */
    private function removeAllZeroRows(array $rows): array
    {
        return array_values(array_filter($rows, function (array $row) {
            $target = (float) ($row[self::TARGET_COLUMN] ?? 0);
            if ($target !== 0.0) {
                return true;
            }
            foreach (self::FEATURE_COLUMNS as $col) {
                if ((float) ($row[$col] ?? 0) !== 0.0) {
                    return true;
                }
            }
            return false;
        }));
    }

    /**
     * Compute variance of a numeric array (0 = constant column).
     */
    private function variance(array $values): float
    {
        $n = count($values);
        if ($n < 2) {
            return 0.0;
        }
        $mean = array_sum($values) / $n;
        $sumSq = 0.0;
        foreach ($values as $v) {
            $sumSq += ($v - $mean) ** 2;
        }
        return $sumSq / ($n - 1);
    }

    /**
     * Pearson correlation between two arrays.
     */
    private function correlation(array $a, array $b): float
    {
        $n = count($a);
        if ($n !== count($b) || $n < 2) {
            return 0.0;
        }
        $meanA = array_sum($a) / $n;
        $meanB = array_sum($b) / $n;
        $num = 0.0;
        $denA = 0.0;
        $denB = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $da = $a[$i] - $meanA;
            $db = $b[$i] - $meanB;
            $num += $da * $db;
            $denA += $da * $da;
            $denB += $db * $db;
        }
        $den = sqrt($denA * $denB);
        return $den > 1e-20 ? ($num / $den) : 0.0;
    }

    /**
     * Remove predictors with zero variance from usedFeatures and X.
     * Returns [usedFeatures, X, removedNames].
     *
     * @param array<int, array<string, float>> $X
     * @return array{0: array<string>, 1: array<int, array<string, float>>, 2: array<string>}
     */
    private function removeConstantColumns(array $usedFeatures, array $X, array $y): array
    {
        $removed = [];
        $keep = [];
        foreach ($usedFeatures as $col) {
            $colValues = array_map(fn (array $row) => $row[$col], $X);
            if ($this->variance($colValues) < 1e-20) {
                $removed[] = $col;
            } else {
                $keep[] = $col;
            }
        }
        $XNew = [];
        foreach ($X as $row) {
            $xRow = [];
            foreach ($keep as $c) {
                $xRow[$c] = $row[$c];
            }
            $XNew[] = $xRow;
        }
        return [$keep, $XNew, $removed];
    }

    /**
     * Drop one from each pair of (near-)perfectly correlated predictors (|r| >= 0.999).
     * Returns [usedFeatures, X, removedNames].
     *
     * @param array<int, array<string, float>> $X
     * @return array{0: array<string>, 1: array<int, array<string, float>>, 2: array<string>}
     */
    private function removeCollinearPredictors(array $usedFeatures, array $X): array
    {
        $removed = [];
        $keep = [];
        $cols = $usedFeatures;
        $colArrays = [];
        foreach ($cols as $c) {
            $colArrays[$c] = array_map(fn (array $row) => $row[$c], $X);
        }
        foreach ($cols as $i => $colA) {
            if (in_array($colA, $removed, true)) {
                continue;
            }
            $drop = false;
            foreach ($keep as $colB) {
                $r = $this->correlation($colArrays[$colA], $colArrays[$colB]);
                if (abs($r) >= 0.999) {
                    $removed[] = $colA;
                    $drop = true;
                    break;
                }
            }
            if (! $drop) {
                $keep[] = $colA;
            }
        }
        $XNew = [];
        foreach ($X as $row) {
            $xRow = [];
            foreach ($keep as $c) {
                $xRow[$c] = $row[$c];
            }
            $XNew[] = $xRow;
        }
        return [$keep, $XNew, $removed];
    }

    /**
     * Reduce predictors so that validRows >= predictorsUsed + 2.
     * Keeps predictors with highest absolute correlation with y.
     *
     * @param array<int, array<string, float>> $X
     */
    private function reducePredictorsToFit(array $usedFeatures, array $X, array $y, int $n): array
    {
        $maxPredictors = max(1, $n - 2);
        if (count($usedFeatures) <= $maxPredictors) {
            return $usedFeatures;
        }
        $correlations = [];
        foreach ($usedFeatures as $col) {
            $colVals = array_map(fn (array $row) => $row[$col], $X);
            $correlations[$col] = abs($this->correlation($colVals, $y));
        }
        arsort($correlations);
        return array_slice(array_keys($correlations), 0, $maxPredictors);
    }

    /**
     * Run multiple linear regression on submitted table data.
     * Filters invalid rows, removes constant/collinear predictors, enforces n >= p+2, retries on singular.
     */
    public function runRegression(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'rows' => ['required', 'array', 'min:1'],
            'rows.*' => ['required', 'array'],
        ]);

        $rows = $validated['rows'];
        $warnings = [];

        $filteredRows = $this->filterCompleteRows($rows);
        $removedZeroRows = count($rows) - count($filteredRows);
        if ($removedZeroRows > 0) {
            $warnings[] = 'Removed empty or placeholder rows (including all-zero rows).';
        }

        if (count($filteredRows) < self::MIN_VALID_ROWS) {
            throw ValidationException::withMessages([
                'rows' => ['At least 5 rows with valid Soil_Loss_Sqm and complete predictor data are required to run regression.'],
            ]);
        }

        $n = count($filteredRows);
        $maxPredictors = max(1, $n - 2);
        $usedFeatures = [];

        foreach (self::FEATURE_COLUMNS as $col) {
            if (count($usedFeatures) >= $maxPredictors) {
                break;
            }
            $hasValues = false;
            foreach ($filteredRows as $row) {
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

        $XFull = [];
        foreach ($filteredRows as $row) {
            $targetVal = $row[self::TARGET_COLUMN];
            $xRow = [];
            foreach ($usedFeatures as $col) {
                $xRow[$col] = (float) ($row[$col] ?? 0);
            }
            $X[] = $xRow;
            $y[] = (float) $targetVal;
            $xFullRow = [];
            foreach (self::FEATURE_COLUMNS as $col) {
                $xFullRow[$col] = (float) ($row[$col] ?? 0);
            }
            $XFull[] = $xFullRow;
        }

        if (count($usedFeatures) < 1) {
            throw ValidationException::withMessages([
                'rows' => ['At least one predictor column must have values.'],
            ]);
        }

        // Remove constant columns
        [$usedFeatures, $X, $removedConstant] = $this->removeConstantColumns($usedFeatures, $X, $y);
        if (count($removedConstant) > 0) {
            $warnings[] = 'Removed constant predictors: ' . implode(', ', $removedConstant) . '.';
        }

        // Remove (near-)perfectly correlated predictors
        [$usedFeatures, $X, $removedCollinear] = $this->removeCollinearPredictors($usedFeatures, $X);
        if (count($removedCollinear) > 0) {
            $warnings[] = 'Removed collinear predictors: ' . implode(', ', $removedCollinear) . '.';
        }

        // Enforce validRows >= predictorsUsed + 2
        if (count($usedFeatures) > $maxPredictors) {
            $usedFeatures = $this->reducePredictorsToFit($usedFeatures, $X, $y, $n);
            $X = array_map(fn (array $row) => array_intersect_key($row, array_flip($usedFeatures)), $X);
            $warnings[] = 'Too many predictors for the number of rows. Kept the most relevant predictors.';
        }

        if (count($usedFeatures) < 1) {
            return response()->json([
                'success' => false,
                'message' => 'Regression cannot run because all predictors are constant or duplicated. Add more varied data or different predictors.',
            ], 422);
        }

        $p = count($usedFeatures) + 1;
        if ($n <= $p) {
            return response()->json([
                'success' => false,
                'message' => 'Too many predictors for the number of rows. Reduce predictors or add more data rows (need at least ' . ($p + 1) . ' valid rows for ' . count($usedFeatures) . ' predictors).',
            ], 422);
        }

        $run = function () use ($usedFeatures, $X, $y) {
            return $this->regression->run($usedFeatures, $X, $y);
        };

        try {
            $result = $run();
        } catch (\RuntimeException $e) {
            $msg = $e->getMessage();
            $isSingular = str_contains($msg, 'singular') || str_contains($msg, 'inverse');
            if ($isSingular) {
                return response()->json([
                    'success' => false,
                    'message' => 'Regression cannot run because some predictors are constant or duplicated. Try removing constant or redundant columns or adding more rows with varied values.',
                    'warnings' => $warnings,
                ], 422);
            }
            return response()->json([
                'success' => false,
                'message' => 'Regression failed: ' . $msg,
                'warnings' => $warnings,
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Regression failed: ' . $e->getMessage(),
                'warnings' => $warnings,
            ], 422);
        }

        if (! empty($result['ridge_used'] ?? false)) {
            $warnings[] = 'Ridge regularization was used due to multicollinearity.';
        }
        unset($result['ridge_used']); // do not expose internal flag in equation payload

        $aux = null;
        if (count($usedFeatures) < count(self::FEATURE_COLUMNS) || $n < count(self::FEATURE_COLUMNS)) {
            try {
                $aux = $this->regression->runRidgeBootstrap(
                    array_values(self::FEATURE_COLUMNS),
                    $XFull,
                    $y,
                    0.001,
                    200
                );
                $aux['standard_errors'] = array_map(fn ($v) => round($v, 6), $aux['standard_errors']);
                $aux['t_statistics'] = array_map(fn ($v) => round($v, 4), $aux['t_statistics']);
                $aux['p_values'] = array_map(fn ($v) => round(max(0, min(1, $v)), 6), $aux['p_values']);
            } catch (\Throwable $e) {
                // Leave aux null; table will show placeholders for dropped predictors
            }
        }

        $payload = [
            'success' => true,
            'regression' => $result,
            'warnings' => $warnings,
        ];
        if ($aux !== null) {
            $payload['aux'] = $aux;
        }

        return response()->json($payload);
    }
}
