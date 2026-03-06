<?php

namespace App\Http\Controllers;

use App\Services\RegressionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ModelBuilderController extends Controller
{
    /** All candidate predictor column names (Year excluded by default; user can include via predictor selection). */
    private const ALL_PREDICTOR_COLUMNS = [
        'Year',
        'Trop_Depressions',
        'Trop_Storms',
        'Sev_Trop_Storms',
        'Typhoons',
        'Super_Typhoons',
        'Floods',
        'Storm_Surges',
        'Precipitation_mm',
        'Seawall_m',
        'Veg_Area_Sqm',
        'Coastal_Elevation',
    ];

    /** Default predictors (all except Year) for SPSS-aligned stepwise. */
    private const DEFAULT_PREDICTOR_COLUMNS = [
        'Trop_Depressions',
        'Trop_Storms',
        'Sev_Trop_Storms',
        'Typhoons',
        'Super_Typhoons',
        'Floods',
        'Storm_Surges',
        'Precipitation_mm',
        'Seawall_m',
        'Veg_Area_Sqm',
        'Coastal_Elevation',
    ];

    /** Target column (y) */
    private const TARGET_COLUMN = 'Soil_Loss_Sqm';

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
     * Keep only rows that have valid target and numeric values for all selected predictor columns.
     * Excludes: missing/blank target, target === 0 (placeholder), any selected predictor missing/blank,
     * and rows that are entirely zeros (target + all selected predictors zero).
     *
     * @param array<int, array<string, mixed>> $rows
     * @param array<string> $predictorColumns Column names to require as numeric
     */
    private function filterCompleteRows(array $rows, array $predictorColumns): array
    {
        $out = [];
        foreach ($rows as $row) {
            $targetVal = $row[self::TARGET_COLUMN] ?? null;
            if ($targetVal === '' || $targetVal === null || ! is_numeric($targetVal)) {
                continue;
            }
            $targetFloat = (float) $targetVal;
            if ($targetFloat === 0.0) {
                continue;
            }
            $complete = true;
            foreach ($predictorColumns as $col) {
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

        return $this->removeAllZeroRows($out, $predictorColumns);
    }

    /**
     * Remove rows where target and all selected predictor columns are zero.
     *
     * @param array<int, array<string, mixed>> $rows
     * @param array<string> $predictorColumns
     */
    private function removeAllZeroRows(array $rows, array $predictorColumns): array
    {
        return array_values(array_filter($rows, function (array $row) use ($predictorColumns) {
            $target = (float) ($row[self::TARGET_COLUMN] ?? 0);
            if ($target !== 0.0) {
                return true;
            }
            foreach ($predictorColumns as $col) {
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
     * Supports Enter (all selected predictors) and Stepwise (SPSS-style).
     * Filters invalid rows, removes constant/collinear predictors, enforces n >= p+2.
     */
    public function runRegression(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'rows' => ['required', 'array', 'min:1'],
            'rows.*' => ['required', 'array'],
            'regression_method' => ['sometimes', 'string', 'in:enter,stepwise'],
            'entry_p' => ['sometimes', 'numeric', 'min:0.01', 'max:0.5'],
            'removal_p' => ['sometimes', 'numeric', 'min:0.05', 'max:0.5'],
            'dependent_variable' => ['sometimes', 'string', 'max:64'],
            'selected_predictors' => ['sometimes', 'array'],
            'selected_predictors.*' => ['string', 'max:64'],
        ]);

        $rows = $validated['rows'];
        $regressionMethod = $validated['regression_method'] ?? 'stepwise';
        $entryP = isset($validated['entry_p']) ? (float) $validated['entry_p'] : RegressionService::STEPWISE_ENTRY_P;
        $removalP = isset($validated['removal_p']) ? (float) $validated['removal_p'] : RegressionService::STEPWISE_REMOVAL_P;
        $dependentVariable = $validated['dependent_variable'] ?? self::TARGET_COLUMN;
        $selectedPredictors = $validated['selected_predictors'] ?? self::DEFAULT_PREDICTOR_COLUMNS;

        // Normalize and validate predictor names
        $selectedPredictors = array_values(array_unique(array_map('strval', $selectedPredictors)));
        $allowedPredictors = array_flip(self::ALL_PREDICTOR_COLUMNS);
        $usedFeatures = [];
        foreach ($selectedPredictors as $col) {
            if (isset($allowedPredictors[$col])) {
                $usedFeatures[] = $col;
            }
        }
        if (count($usedFeatures) === 0) {
            $usedFeatures = self::DEFAULT_PREDICTOR_COLUMNS;
        }

        if ($dependentVariable !== self::TARGET_COLUMN) {
            throw ValidationException::withMessages([
                'dependent_variable' => ['Only Soil_Loss_Sqm is supported as the dependent variable.'],
            ]);
        }

        $warnings = [];
        $filteredRows = $this->filterCompleteRows($rows, $usedFeatures);
        $removedZeroRows = count($rows) - count($filteredRows);
        if ($removedZeroRows > 0) {
            $warnings[] = 'Removed empty or placeholder rows (including all-zero rows).';
        }

        if (count($filteredRows) < self::MIN_VALID_ROWS) {
            throw ValidationException::withMessages([
                'rows' => ['At least 5 rows with valid ' . self::TARGET_COLUMN . ' and complete predictor data are required to run regression.'],
            ]);
        }

        $n = count($filteredRows);
        $X = [];
        $y = [];
        foreach ($filteredRows as $row) {
            $xRow = [];
            foreach ($usedFeatures as $col) {
                $xRow[$col] = (float) ($row[$col] ?? 0);
            }
            $X[] = $xRow;
            $y[] = (float) ($row[self::TARGET_COLUMN]);
        }

        [$usedFeatures, $X, $removedConstant] = $this->removeConstantColumns($usedFeatures, $X, $y);
        if (count($removedConstant) > 0) {
            $warnings[] = 'Removed constant predictors: ' . implode(', ', $removedConstant) . '.';
        }

        [$usedFeatures, $X, $removedCollinear] = $this->removeCollinearPredictors($usedFeatures, $X);
        if (count($removedCollinear) > 0) {
            $warnings[] = 'Removed collinear predictors: ' . implode(', ', $removedCollinear) . '.';
        }

        $maxPredictors = max(1, $n - 2);
        if (count($usedFeatures) > $maxPredictors) {
            $usedFeatures = $this->reducePredictorsToFit($usedFeatures, $X, $y, $n);
            $X = array_map(fn (array $row) => array_intersect_key($row, array_flip($usedFeatures)), $X);
            $warnings[] = 'Too many predictors for the number of rows. Using the ' . $maxPredictors . ' most relevant candidates.';
        }

        if (count($usedFeatures) < 1) {
            return response()->json([
                'success' => false,
                'message' => 'Regression cannot run because all predictors are constant or duplicated. Add more varied data or different predictors.',
            ], 422);
        }

        $dependentVarName = 'Soil Loss';

        try {
            if ($regressionMethod === 'enter') {
                $result = $this->regression->runOlsStrict($usedFeatures, $X, $y, true, $dependentVarName);
                $result['stepwise_mode'] = false;
                $result['selected_predictors'] = $usedFeatures;
                $result['step_log'] = [];
                $result['validation'] = array_merge(
                    $result['validation'] ?? [],
                    [
                        'stepwise_settings' => null,
                        'correlations' => [],
                        'simple_regressions' => [],
                    ],
                    $this->regression->buildValidationFromOlsResult($result, $X, $y, $usedFeatures)
                );
            } else {
                $result = $this->regression->runStepwise($usedFeatures, $X, $y, $entryP, $removalP, $dependentVarName);
            }
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
        unset($result['ridge_used']);

        $settings = $result['validation']['stepwise_settings'] ?? [];
        if (! empty($settings) && ((float) ($settings['entry_p'] ?? 0) !== 0.05 || (float) ($settings['removal_p'] ?? 0) !== 0.10)) {
            $warnings[] = 'Custom stepwise thresholds used (not SPSS default 0.05/0.10).';
        }

        return response()->json([
            'success' => true,
            'regression' => $result,
            'warnings' => $warnings,
        ]);
    }
}
