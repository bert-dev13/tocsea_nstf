<?php

namespace App\Services;

/**
 * Multiple Linear Regression using Ordinary Least Squares (OLS).
 * Solves: y = Xβ + ε where β = (X'X)^{-1} X'y
 * Uses pseudo-inverse / Ridge fallback when X'X is singular (unless strict OLS).
 * Computes standard errors, t-statistics, p-values, adjusted R², ANOVA, and standard error of estimate.
 */
class RegressionService
{
    private const RIDGE_LAMBDA = 1e-8;

    /** SPSS default: Probability of F to Enter = 0.05, Probability of F to Remove = 0.10 */
    public const STEPWISE_ENTRY_P = 0.05;
    public const STEPWISE_REMOVAL_P = 0.10;

    /**
     * Run multiple linear regression on the given data.
     * Uses Ridge fallback when X'X is singular.
     *
     * @param array<string> $featureNames Column names for X (excluding intercept)
     * @param array<array<float>> $X 2D array of predictor values [row][col]
     * @param array<float> $y 1D array of target values
     * @return array{
     *   intercept: float,
     *   coefficients: array<string, float>,
     *   standard_errors: array<string, float>,
     *   t_statistics: array<string, float>,
     *   p_values: array<string, float>,
     *   r_squared: float,
     *   adjusted_r_squared: float,
     *   rmse: float,
     *   mae: float,
     *   equation: string,
     *   df_residual: int,
     *   standard_error_of_estimate: float,
     *   anova: array{F: float, df_regression: int, df_residual: int, p_value: float},
     *   ridge_used?: bool
     * }
     */
    public function run(array $featureNames, array $X, array $y, string $dependentVarName = 'Soil Loss'): array
    {
        return $this->runOlsInternal($featureNames, $X, $y, true, true, $dependentVarName);
    }

    /**
     * Run OLS without Ridge fallback. Throws on singular X'X.
     * Used by Stepwise (SPSS-style) so the official model is pure OLS.
     *
     * @param array<string> $featureNames
     * @param array<array<float>> $X 2D [row][col] matching featureNames
     * @param array<float> $y
     * @param bool $roundOutput When false, p_values and related stats are not rounded (for stepwise entry/removal decisions).
     * @param string $dependentVarName Display name for dependent variable in equation
     * @return array Same shape as run(), no ridge_used
     */
    public function runOlsStrict(array $featureNames, array $X, array $y, bool $roundOutput = true, string $dependentVarName = 'Soil Loss'): array
    {
        return $this->runOlsInternal($featureNames, $X, $y, false, $roundOutput, $dependentVarName);
    }

    /**
     * Stepwise regression (SPSS-style) using partial F for entry and removal.
     *
     * Procedure:
     * 1. Entry: For each candidate, partial F = (SSR_new − SSR_old) / (df_new − df_old) / (SSE_new / df_residual_new).
     *    Select the predictor with the largest partial F among those that meet the entry rule. Add only if p ≤ entryP.
     * 2. After each entry: recompute full OLS model, then for each included predictor compute partial F for removal
     *    (SSE_without − SSE_with) / 1 / (SSE_with / df). Remove if p ≥ removalP.
     * 3. Repeat until no candidate meets entry and no included predictor meets removal.
     *
     * Entry/removal decisions are based only on partial F p-values, not on coefficient p-values alone.
     * Uses OLS only (no Ridge). Final equation contains only predictors retained after the full procedure.
     *
     * @param array<string> $featureNames All candidate predictor names (order must match columns in $X)
     * @param array<array<float>> $X 2D array [row][col] with columns in same order as featureNames
     * @param array<float> $y Target
     * @param float $entryP Probability of F to Enter (p <= this to enter)
     * @param float $removalP Probability of F to Remove (p >= this to remove)
     * @param string $dependentVarName Display name for dependent variable in equation
     * @return array Same structure as run(), plus stepwise_mode: true, selected_predictors: list, step_log: list, validation: object
     */
    public function runStepwise(array $featureNames, array $X, array $y, float $entryP = self::STEPWISE_ENTRY_P, float $removalP = self::STEPWISE_REMOVAL_P, string $dependentVarName = 'Soil Loss'): array
    {
        $n = count($y);
        if ($n < 2 || count($X) !== $n) {
            throw new \InvalidArgumentException('X and y must have the same number of rows and at least 2 observations.');
        }

        $candidates = array_values($featureNames);
        $included = [];
        $stepLog = [];
        $stepNumber = 0;

        $yMean = array_sum($y) / $n;
        $sst = 0.0;
        foreach ($y as $yi) {
            $sst += ($yi - $yMean) ** 2;
        }

        $validation = [
            'correlations' => [],
            'simple_regressions' => [],
            'stepwise_settings' => ['entry_p' => $entryP, 'removal_p' => $removalP],
        ];
        foreach ($candidates as $name) {
            $col = array_map(fn (array $row) => (float) ($row[$name] ?? 0), $X);
            $validation['correlations'][$name] = $this->pearsonCorrelation($col, $y);
            try {
                $XOne = $this->extractColumns($X, $featureNames, [$name]);
                $fitOne = $this->runOlsStrict([$name], $XOne, $y, false);
                $validation['simple_regressions'][] = [
                    'variable' => $name,
                    'r_squared' => $fitOne['r_squared'],
                    'intercept' => $fitOne['intercept'],
                    'coefficient' => $fitOne['coefficients'][$name] ?? 0,
                    'p_value' => $fitOne['p_values'][$name] ?? 1.0,
                    't' => $fitOne['t_statistics'][$name] ?? 0,
                ];
            } catch (\Throwable $e) {
                $validation['simple_regressions'][] = ['variable' => $name, 'error' => $e->getMessage()];
            }
        }

        while (true) {
            // --- ENTRY: Partial F = (SSR_new - SSR_old) / (df_new - df_old) / (SSE_new / df_residual_new). Select largest F; add only if p ≤ entryP. ---
            $ssrOld = 0.0;
            $dfOld = 0;
            $fitReduced = null;
            if (count($included) > 0) {
                $XRed = $this->extractColumns($X, $featureNames, $included);
                try {
                    $fitReduced = $this->runOlsStrict($included, $XRed, $y, false);
                    $ssrOld = $fitReduced['anova']['ss_regression'] ?? 0.0;
                    $dfOld = $fitReduced['anova']['df_regression'] ?? 0;
                } catch (\RuntimeException $e) {
                    $fitReduced = null;
                }
            }

            $bestName = null;
            $bestPartialF = -1.0;
            $bestFPValue = 1.0;
            $candidatesTried = [];

            foreach ($candidates as $name) {
                if (in_array($name, $included, true)) {
                    continue;
                }
                $tryIncluded = array_merge($included, [$name]);
                $tryIncluded = array_values($tryIncluded);
                $XSub = $this->extractColumns($X, $featureNames, $tryIncluded);
                try {
                    $fit = $this->runOlsStrict($tryIncluded, $XSub, $y, false);
                } catch (\RuntimeException $e) {
                    $candidatesTried[] = [
                        'variable' => $name,
                        'partial_f' => null,
                        'f_p_value' => 1.0,
                        'entry_decision' => 'rejected',
                        'rejected_reason' => 'singular model',
                    ];
                    continue;
                }
                $ssrNew = $fit['anova']['ss_regression'] ?? 0.0;
                $sseNew = $fit['anova']['ss_residual'] ?? 0.0;
                $dfResidualNew = $fit['df_residual'] ?? max(1, $n - count($tryIncluded) - 1);
                $dfNew = $fit['anova']['df_regression'] ?? (count($tryIncluded));
                $dfDiff = $dfNew - $dfOld;
                $partialF = null;
                $fPValue = 1.0;
                if ($dfDiff > 0 && $dfResidualNew > 0 && $sseNew > 1e-30) {
                    $msResidualNew = $sseNew / $dfResidualNew;
                    $partialF = (($ssrNew - $ssrOld) / $dfDiff) / $msResidualNew;
                    $fPValue = $this->fTestPValue($partialF, (int) $dfDiff, $dfResidualNew);
                }
                $meetsEntry = $partialF !== null && $fPValue <= $entryP;
                $entryDecision = $meetsEntry ? 'meets_entry' : 'rejected';
                $rejectedReason = ! $meetsEntry && $partialF !== null
                    ? 'Probability of F (' . round($fPValue, 6) . ') > entry threshold (' . $entryP . ')'
                    : ($partialF === null ? 'could not compute partial F' : null);
                $candidatesTried[] = [
                    'variable' => $name,
                    'partial_f' => $partialF !== null ? round($partialF, 6) : null,
                    'f_p_value' => round($fPValue, 6),
                    'entry_decision' => $entryDecision,
                    'rejected_reason' => $rejectedReason,
                ];
                if ($partialF !== null && $partialF > $bestPartialF && $fPValue <= $entryP) {
                    $bestPartialF = $partialF;
                    $bestFPValue = $fPValue;
                    $bestName = $name;
                }
            }

            if ($bestName === null) {
                if (count($included) > 0 && count($candidatesTried) > 0) {
                    $stepNumber++;
                    $stepLog[] = [
                        'step' => $stepNumber,
                        'action' => 'no_entry',
                        'candidates_tested' => $candidatesTried,
                        'entry_decision' => 'no candidate meets entry criterion',
                        'retained' => $included,
                    ];
                }
                break;
            }

            $stepNumber++;
            $stepLog[] = [
                'step' => $stepNumber,
                'action' => 'entered',
                'variable' => $bestName,
                'partial_f' => round($bestPartialF, 6),
                'f_p_value' => round($bestFPValue, 6),
                'candidates_tested' => $candidatesTried,
                'entry_decision' => 'entered (largest partial F meeting entry rule)',
                'retained' => array_merge($included, [$bestName]),
            ];
            $included[] = $bestName;
            $included = array_values($included);

            // --- REMOVAL: After each entry, test each included predictor for removal. Partial F for removal = (SSE_without - SSE_with)/1 / (SSE_with/df). Remove if p >= removalP. ---
            $removalChecks = [];
            while (count($included) > 0) {
                $XFull = $this->extractColumns($X, $featureNames, $included);
                $fitFull = null;
                try {
                    $fitFull = $this->runOlsStrict($included, $XFull, $y, false);
                } catch (\RuntimeException $e) {
                    break;
                }
                $sseFull = $fitFull['anova']['ss_residual'] ?? 0.0;
                $dfFull = $fitFull['df_residual'] ?? 0;
                $toRemove = null;
                $worstP = $removalP - 1e-10;
                foreach ($included as $name) {
                    $without = array_values(array_filter($included, fn ($x) => $x !== $name));
                    $XWithout = $this->extractColumns($X, $featureNames, $without);
                    try {
                        $fitWithout = $this->runOlsStrict($without, $XWithout, $y, false);
                    } catch (\RuntimeException $e) {
                        continue;
                    }
                    $sseWithout = $fitWithout['anova']['ss_residual'] ?? $sseFull;
                    $partialFRemove = null;
                    $fpRemove = 1.0;
                    if ($dfFull > 0 && $sseFull > 1e-30 && ($sseWithout - $sseFull) > -1e-10) {
                        $partialFRemove = (($sseWithout - $sseFull) / 1.0) / ($sseFull / $dfFull);
                        $fpRemove = $this->fTestPValue($partialFRemove, 1, $dfFull);
                    }
                    $remove = $fpRemove >= $removalP;
                    $removalChecks[] = [
                        'variable' => $name,
                        'partial_f' => $partialFRemove !== null ? round($partialFRemove, 6) : null,
                        'f_p_value' => round($fpRemove, 6),
                        'removal_decision' => $remove ? 'removed' : 'retained',
                        'reason' => $remove ? 'Probability of F >= removal threshold (' . $removalP . ')' : 'Probability of F < removal threshold',
                    ];
                    if ($remove && $fpRemove > $worstP) {
                        $worstP = $fpRemove;
                        $toRemove = $name;
                    }
                }
                $stepLog[count($stepLog) - 1]['removal_checks'] = $removalChecks;
                if ($toRemove === null) {
                    break;
                }
                $stepNumber++;
                $stepLog[] = [
                    'step' => $stepNumber,
                    'action' => 'removed',
                    'variable' => $toRemove,
                    'retained' => array_values(array_filter($included, fn ($x) => $x !== $toRemove)),
                ];
                $included = array_values(array_filter($included, fn ($x) => $x !== $toRemove));
                $removalChecks = [];
            }
        }

        // Final model: intercept-only if nothing included
        if (count($included) === 0) {
            $intercept = array_sum($y) / $n;
            $yPred = array_fill(0, $n, $intercept);
            $yMean = $intercept;
            $sse = 0.0;
            $sst = 0.0;
            foreach ($y as $i => $yi) {
                $sst += ($yi - $yMean) ** 2;
                $sse += ($yi - $yPred[$i]) ** 2;
            }
            $ssr = 0.0;
            $rSquared = $sst > 0 ? $ssr / $sst : 0.0;
            $dfResidual = $n - 1;
            $adjustedRSquared = $n > 1 ? 1.0 - (1.0 - $rSquared) * ($n - 1) / $dfResidual : 0.0;
            $stdErrEst = ($n > 1) ? sqrt($sse / ($n - 1)) : 0.0;
            $residuals = [];
            foreach ($y as $i => $yi) {
                $residuals[] = $yi - $yPred[$i];
            }
            $equation = $dependentVarName . ' = ' . $this->formatNum($intercept);
            $result = [
                'intercept' => $intercept,
                'coefficients' => [],
                'standard_errors' => [],
                't_statistics' => [],
                'p_values' => [],
                'r_squared' => $rSquared,
                'adjusted_r_squared' => $adjustedRSquared,
                'rmse' => sqrt($sse / $n),
                'mae' => $this->mae($y, $yPred),
                'equation' => $equation,
                'df_residual' => $dfResidual,
                'standard_error_of_estimate' => $stdErrEst,
                'predicted_values' => array_values($yPred),
                'residuals' => $residuals,
                'anova' => [
                    'ss_regression' => $ssr,
                    'ss_residual' => $sse,
                    'ss_total' => $sst,
                    'ms_regression' => 0.0,
                    'ms_residual' => $dfResidual > 0 ? $sse / $dfResidual : 0.0,
                    'F' => 0.0,
                    'df_regression' => 0,
                    'df_residual' => $dfResidual,
                    'p_value' => 1.0,
                ],
                'stepwise_mode' => true,
                'selected_predictors' => [],
                'step_log' => $stepLog,
                'validation' => $validation,
            ];
            $emptyX = array_fill(0, $n, []);
            $validation = array_merge($validation, $this->buildValidationFromResult($result, $emptyX, $y, [], $X, $featureNames));
            $result['validation'] = $validation;
            return $result;
        }

        $XFinal = $this->extractColumns($X, $featureNames, $included);
        $result = $this->runOlsStrict($included, $XFinal, $y, false, $dependentVarName);
        $result['stepwise_mode'] = true;
        $result['selected_predictors'] = $included;
        $result['step_log'] = $stepLog;
        $validation = array_merge($validation, $this->buildValidationFromResult($result, $XFinal, $y, $included, $X, $featureNames));
        $result['validation'] = $validation;
        return $result;
    }

    /**
     * Build developer validation block: full-precision and matrix data for line-by-line SPSS comparison.
     *
     * @param array $result OLS result (intercept, coefficients, anova, predicted_values, residuals, etc.)
     * @param array<array<float>> $selectedX Predictor matrix used in the model (rows × selected columns)
     * @param array<float> $y Dependent variable vector
     * @param array<string> $selectedNames Names of predictors in the model
     * @param array<array<float>>|null $fullX Full candidate X (for stepwise); if null, use selectedX
     * @param array<string>|null $fullNames Full candidate names; if null, use selectedNames
     */
    private function buildValidationFromResult(array $result, array $selectedX, array $y, array $selectedNames, ?array $fullX = null, ?array $fullNames = null): array
    {
        $fullX = $fullX ?? $selectedX;
        $fullNames = $fullNames ?? $selectedNames;
        $anova = $result['anova'] ?? [];
        return [
            'parsed_input_matrix' => $fullX,
            'dependent_variable_vector' => $y,
            'selected_predictor_names' => $selectedNames,
            'selected_predictor_matrix' => $selectedX,
            'coefficients_full_precision' => $result['coefficients'] ?? [],
            'intercept_full_precision' => $result['intercept'] ?? 0.0,
            'y_mean' => count($y) > 0 ? array_sum($y) / count($y) : 0.0,
            'predicted_values_full_precision' => $result['predicted_values'] ?? [],
            'residuals_full_precision' => $result['residuals'] ?? [],
            'sst' => $anova['ss_total'] ?? 0.0,
            'ssr' => $anova['ss_regression'] ?? 0.0,
            'sse' => $anova['ss_residual'] ?? 0.0,
            'r_squared' => $result['r_squared'] ?? 0.0,
            'adjusted_r_squared' => $result['adjusted_r_squared'] ?? 0.0,
            'standard_error_of_estimate' => $result['standard_error_of_estimate'] ?? 0.0,
            'coefficient_standard_errors' => $result['standard_errors'] ?? [],
            't_values' => $result['t_statistics'] ?? [],
            'coefficient_p_values' => $result['p_values'] ?? [],
            'model_F' => $anova['F'] ?? 0.0,
            'model_p_value' => $anova['p_value'] ?? 1.0,
        ];
    }

    /**
     * Build developer validation block for an OLS result (e.g. Enter method). Public for controller use.
     */
    public function buildValidationFromOlsResult(array $result, array $X, array $y, array $featureNames): array
    {
        return $this->buildValidationFromResult($result, $X, $y, $featureNames, $X, $featureNames);
    }

    /**
     * Pearson correlation between two vectors (full precision).
     */
    private function pearsonCorrelation(array $x, array $y): float
    {
        $n = count($x);
        if ($n !== count($y) || $n < 2) {
            return 0.0;
        }
        $meanX = array_sum($x) / $n;
        $meanY = array_sum($y) / $n;
        $num = 0.0;
        $denX = 0.0;
        $denY = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $dx = $x[$i] - $meanX;
            $dy = $y[$i] - $meanY;
            $num += $dx * $dy;
            $denX += $dx * $dx;
            $denY += $dy * $dy;
        }
        $den = sqrt($denX * $denY);
        return $den > 1e-20 ? ($num / $den) : 0.0;
    }

    /**
     * Build X matrix with only the selected columns (order preserved).
     * Rows may be associative (keyed by feature name) or indexed.
     *
     * @param array<array<float>> $X Full X
     * @param array<string> $featureNames Full list (used only when row is indexed)
     * @param array<string> $selected Subset of featureNames
     * @return array<array<float>>
     */
    private function extractColumns(array $X, array $featureNames, array $selected): array
    {
        $out = [];
        foreach ($X as $row) {
            $vals = array_values($row);
            $isAssoc = array_keys($row) !== range(0, count($row) - 1);
            if ($isAssoc) {
                $out[] = array_map(fn ($name) => isset($row[$name]) ? (float) $row[$name] : 0.0, $selected);
            } else {
                $keyToIdx = array_flip($featureNames);
                $out[] = array_map(fn ($name) => $vals[$keyToIdx[$name] ?? -1] ?? 0.0, $selected);
            }
        }
        return $out;
    }

    /**
     * Internal OLS: β = (X'X)^{-1} X'y, full double precision. No rounding of intermediate or output values.
     * SSE = Σ(Y−Ŷ)², SSR = Σ(Ŷ−Ȳ)², SST = Σ(Y−Ȳ)², R² = SSR/SST, StdError = sqrt(SSE/(n−k−1)).
     *
     * @param array<string> $featureNames
     * @param array<array<float>> $X
     * @param array<float> $y
     * @param bool $allowRidge If false, throw on singular (for stepwise).
     * @param bool $roundOutput Unused; retained for API compatibility. Values are never rounded.
     * @param string $dependentVarName Display name for dependent variable in equation
     * @return array
     */
    private function runOlsInternal(array $featureNames, array $X, array $y, bool $allowRidge, bool $roundOutput = true, string $dependentVarName = 'Soil Loss'): array
    {
        $n = count($y);
        if ($n < 2 || count($X) !== $n) {
            throw new \InvalidArgumentException('X and y must have the same number of rows and at least 2 observations.');
        }

        $designMatrix = [];
        foreach ($X as $i => $row) {
            $designMatrix[$i] = array_merge([1.0], array_values($row));
        }
        $p = count($designMatrix[0]);
        $dfResidual = $n - $p;

        $Xt = $this->transpose($designMatrix);
        $XtX = $this->matrixMultiply($Xt, $designMatrix);
        $XtY = $this->matrixVectorMultiply($Xt, $y);

        $XtXInv = null;
        $ridgeUsed = false;
        try {
            $XtXInv = $this->inverse($XtX);
        } catch (\RuntimeException $e) {
            if (! $allowRidge) {
                throw $e;
            }
            $ridgeUsed = true;
            $XtXRidge = $this->addDiagonal($XtX, self::RIDGE_LAMBDA);
            $XtXInv = $this->inverse($XtXRidge);
        }

        $beta = $this->matrixVectorMultiply($XtXInv, $XtY);

        $intercept = $beta[0];
        $coefficients = [];
        foreach ($featureNames as $j => $name) {
            $coefficients[$name] = $beta[$j + 1] ?? 0;
        }

        $yPred = $this->predict($designMatrix, $beta);

        $yMean = array_sum($y) / $n;
        $sse = 0.0;
        $sst = 0.0;
        $ssr = 0.0;
        foreach ($y as $i => $yi) {
            $sse += ($yi - $yPred[$i]) ** 2;
            $sst += ($yi - $yMean) ** 2;
            $ssr += ($yPred[$i] - $yMean) ** 2;
        }
        $rSquared = $sst > 0 ? $ssr / $sst : 0.0;
        $adjustedRSquared = $this->adjustedRSquared($rSquared, $n, $p);
        $rmse = sqrt($sse / $n);
        $mae = $this->mae($y, $yPred);

        $k = $p - 1;
        $standardErrorOfEstimate = ($n > $k + 1 && $sse >= 0) ? sqrt($sse / ($n - $k - 1)) : 0.0;
        $sigmaSq = $dfResidual > 0 ? $sse / $dfResidual : 0.0;

        $standardErrors = [];
        $tStatistics = [];
        $pValues = [];
        foreach ($featureNames as $j => $name) {
            $idx = $j + 1;
            $varBeta = $sigmaSq * ($XtXInv[$idx][$idx] ?? 0);
            $se = $varBeta > 0 ? sqrt($varBeta) : 0.0;
            $standardErrors[$name] = $se;
            $t = $se > 1e-10 ? ($coefficients[$name] / $se) : 0.0;
            $tStatistics[$name] = $t;
            $pValues[$name] = $this->twoTailedTPValue($t, $dfResidual);
        }

        $equation = $this->buildEquationString($intercept, $coefficients, $dependentVarName);

        $dfRegression = $p - 1;
        $msReg = $dfRegression > 0 ? $ssr / $dfRegression : 0.0;
        $msRes = $dfResidual > 0 ? $sse / $dfResidual : 0.0;
        $F = ($msRes > 1e-20 && $dfRegression > 0) ? $msReg / $msRes : 0.0;
        $anovaP = $this->fTestPValue($F, $dfRegression, $dfResidual);

        $residuals = [];
        foreach ($y as $i => $yi) {
            $residuals[] = $yi - $yPred[$i];
        }
        $predictedValues = array_values($yPred);

        $result = [
            'intercept' => $intercept,
            'coefficients' => $coefficients,
            'standard_errors' => $standardErrors,
            't_statistics' => $tStatistics,
            'p_values' => $pValues,
            'r_squared' => $rSquared,
            'adjusted_r_squared' => $adjustedRSquared,
            'rmse' => $rmse,
            'mae' => $mae,
            'equation' => $equation,
            'df_residual' => $dfResidual,
            'standard_error_of_estimate' => $standardErrorOfEstimate,
            'predicted_values' => $predictedValues,
            'residuals' => $residuals,
            'anova' => [
                'ss_regression' => $ssr,
                'ss_residual' => $sse,
                'ss_total' => $sst,
                'ms_regression' => $msReg,
                'ms_residual' => $msRes,
                'F' => $F,
                'df_regression' => $dfRegression,
                'df_residual' => $dfResidual,
                'p_value' => max(0.0, min(1.0, $anovaP)),
            ],
        ];
        if ($allowRidge && $ridgeUsed) {
            $result['ridge_used'] = true;
        }
        return $result;
    }

    /**
     * P-value for F-statistic (F distribution).
     */
    private function fTestPValue(float $F, int $df1, int $df2): float
    {
        if ($df1 <= 0 || $df2 <= 0 || $F <= 0) {
            return 1.0;
        }
        $x = $df2 / ($df2 + $df1 * $F);
        return $this->regularizedIncompleteBeta($x, $df2 / 2.0, $df1 / 2.0);
    }

    /**
     * Add a constant to the diagonal of a square matrix (for Ridge regularization).
     */
    private function addDiagonal(array $m, float $lambda): array
    {
        $n = count($m);
        $out = [];
        for ($i = 0; $i < $n; $i++) {
            $out[$i] = [];
            for ($j = 0; $j < $n; $j++) {
                $out[$i][$j] = $m[$i][$j] + ($i === $j ? $lambda : 0);
            }
        }
        return $out;
    }

    /**
     * Two-tailed p-value for t-statistic (approximation using normal for large df).
     */
    private function twoTailedTPValue(float $t, int $df): float
    {
        $absT = abs($t);
        if ($df <= 0) {
            return 1.0;
        }
        if ($df >= 30) {
            return 2.0 * (1.0 - $this->normalCdf($absT));
        }
        return $this->tCdfTwoTailed($absT, $df);
    }

    /**
     * Standard normal CDF approximation (Abramowitz & Stegun 26.2.17).
     */
    private function normalCdf(float $z): float
    {
        if ($z <= -8) {
            return 0.0;
        }
        if ($z >= 8) {
            return 1.0;
        }
        $t = 1.0 / (1.0 + 0.2316419 * abs($z));
        $d = 0.3989423 * exp(-$z * $z / 2.0);
        $p = $d * $t * (0.3193815 + $t * (-0.3565638 + $t * (1.781478 + $t * (-1.821256 + $t * 1.330274))));
        return $z > 0 ? 1.0 - $p : $p;
    }

    /**
     * Two-tailed p-value for t-distribution (approximation via incomplete beta).
     */
    private function tCdfTwoTailed(float $t, int $df): float
    {
        $x = $df / ($df + $t * $t);
        $a = $df / 2.0;
        $b = 0.5;
        $betaReg = $this->regularizedIncompleteBeta($x, $a, $b);
        $pRight = 1.0 - 0.5 * $betaReg;
        return 2.0 * (1.0 - $pRight);
    }

    /**
     * Regularized incomplete beta function I(x; a, b) for x in [0,1], a,b > 0.
     */
    private function regularizedIncompleteBeta(float $x, float $a, float $b): float
    {
        if ($x <= 0) {
            return 0.0;
        }
        if ($x >= 1) {
            return 1.0;
        }
        $bt = exp($this->logBeta($a, $b) + $a * log($x) + $b * log(1.0 - $x));
        if ($x < ($a + 1) / ($a + $b + 2)) {
            return $bt * $this->continuedFractionBeta($x, $a, $b) / $a;
        }
        return 1.0 - $bt * $this->continuedFractionBeta(1.0 - $x, $b, $a) / $b;
    }

    private function logBeta(float $a, float $b): float
    {
        return $this->logGamma($a) + $this->logGamma($b) - $this->logGamma($a + $b);
    }

    private function logGamma(float $z): float
    {
        if ($z < 0.5) {
            return log(M_PI / sin(M_PI * $z)) - $this->logGamma(1.0 - $z);
        }
        $z -= 1.0;
        $x = 0.99999999999980993 + 76.18009172947146 / ($z + 1) - 86.50532032941678 / ($z + 2)
            + 24.01409824083091 / ($z + 3) - 1.231739572450155 / ($z + 4) + 0.1208650973866179e-2 / ($z + 5)
            - 0.5395239384953e-5 / ($z + 6);
        return log(2.5066282746310005 * $x) + ($z + 0.5) * log($z + 5.5) - ($z + 5.5);
    }

    private function continuedFractionBeta(float $x, float $a, float $b): float
    {
        $m = 1;
        $aa = 0.0;
        $del = 0.0;
        $qab = $a + $b;
        $qap = $a + 1.0;
        $qam = $a - 1.0;
        $c = 1.0;
        $d = 1.0 - $qab * $x / $qap;
        if (abs($d) < 1e-30) {
            $d = 1e-30;
        }
        $d = 1.0 / $d;
        $h = $d;
        for ($m = 1; $m <= 200; $m++) {
            $m2 = 2 * $m;
            $aa = $m * ($b - $m) * $x / (($qam + $m2) * ($a + $m2));
            $d = 1.0 + $aa * $d;
            if (abs($d) < 1e-30) {
                $d = 1e-30;
            }
            $c = 1.0 + $aa / $c;
            if (abs($c) < 1e-30) {
                $c = 1e-30;
            }
            $d = 1.0 / $d;
            $h *= $d * $c;
            $aa = -($a + $m) * ($qab + $m) * $x / (($a + $m2) * ($qap + $m2));
            $d = 1.0 + $aa * $d;
            if (abs($d) < 1e-30) {
                $d = 1e-30;
            }
            $c = 1.0 + $aa / $c;
            if (abs($c) < 1e-30) {
                $c = 1e-30;
            }
            $d = 1.0 / $d;
            $del = $d * $c;
            $h *= $del;
            if (abs($del - 1.0) < 3e-7) {
                break;
            }
        }
        return $h;
    }

    private function adjustedRSquared(float $rSquared, int $n, int $p): float
    {
        if ($n <= $p) {
            return 0.0;
        }
        return 1.0 - (1.0 - $rSquared) * ($n - 1) / ($n - $p);
    }

    private function transpose(array $m): array
    {
        $rows = count($m);
        $cols = count($m[0]);
        $t = [];
        for ($j = 0; $j < $cols; $j++) {
            $t[$j] = [];
            for ($i = 0; $i < $rows; $i++) {
                $t[$j][$i] = $m[$i][$j];
            }
        }
        return $t;
    }

    private function matrixMultiply(array $A, array $B): array
    {
        $rowsA = count($A);
        $colsA = count($A[0]);
        $colsB = count($B[0]);
        $C = [];
        for ($i = 0; $i < $rowsA; $i++) {
            $C[$i] = [];
            for ($j = 0; $j < $colsB; $j++) {
                $sum = 0.0;
                for ($k = 0; $k < $colsA; $k++) {
                    $sum += (float) $A[$i][$k] * (float) $B[$k][$j];
                }
                $C[$i][$j] = $sum;
            }
        }
        return $C;
    }

    private function matrixVectorMultiply(array $A, array $v): array
    {
        $result = [];
        foreach ($A as $i => $row) {
            $sum = 0.0;
            foreach ($row as $j => $val) {
                $sum += (float) $val * (float) $v[$j];
            }
            $result[$i] = $sum;
        }
        return $result;
    }

    private function inverse(array $m): array
    {
        $n = count($m);
        $aug = [];
        for ($i = 0; $i < $n; $i++) {
            $aug[$i] = array_merge($m[$i], array_fill(0, $n, 0));
            $aug[$i][$n + $i] = 1;
        }
        $this->gaussJordan($aug, $n);
        $inv = [];
        for ($i = 0; $i < $n; $i++) {
            $inv[$i] = array_slice($aug[$i], $n);
        }
        return $inv;
    }

    private function gaussJordan(array &$aug, int $n): void
    {
        for ($col = 0; $col < $n; $col++) {
            $maxRow = $col;
            for ($row = $col + 1; $row < $n; $row++) {
                if (abs($aug[$row][$col]) > abs($aug[$maxRow][$col])) {
                    $maxRow = $row;
                }
            }
            if ($maxRow !== $col) {
                $tmp = $aug[$col];
                $aug[$col] = $aug[$maxRow];
                $aug[$maxRow] = $tmp;
            }
            $pivot = $aug[$col][$col];
            if (abs($pivot) < 1e-10) {
                throw new \RuntimeException('Matrix is singular; cannot compute inverse.');
            }
            for ($j = 0; $j < 2 * $n; $j++) {
                $aug[$col][$j] /= $pivot;
            }
            for ($row = 0; $row < $n; $row++) {
                if ($row !== $col) {
                    $factor = $aug[$row][$col];
                    for ($j = 0; $j < 2 * $n; $j++) {
                        $aug[$row][$j] -= $factor * $aug[$col][$j];
                    }
                }
            }
        }
    }

    private function predict(array $X, array $beta): array
    {
        $result = [];
        foreach ($X as $i => $row) {
            $sum = 0;
            foreach ($row as $j => $val) {
                $sum += $val * $beta[$j];
            }
            $result[$i] = $sum;
        }
        return $result;
    }

    private function rSquared(array $y, array $yPred): float
    {
        $n = count($y);
        $yMean = array_sum($y) / $n;
        $ssTot = 0;
        $ssRes = 0;
        for ($i = 0; $i < $n; $i++) {
            $ssTot += ($y[$i] - $yMean) ** 2;
            $ssRes += ($y[$i] - $yPred[$i]) ** 2;
        }
        return $ssTot > 0 ? 1 - ($ssRes / $ssTot) : 0;
    }

    private function rmse(array $y, array $yPred): float
    {
        $n = count($y);
        $sum = 0;
        for ($i = 0; $i < $n; $i++) {
            $sum += ($y[$i] - $yPred[$i]) ** 2;
        }
        return sqrt($sum / $n);
    }

    private function mae(array $y, array $yPred): float
    {
        $n = count($y);
        $sum = 0;
        for ($i = 0; $i < $n; $i++) {
            $sum += abs($y[$i] - $yPred[$i]);
        }
        return $sum / $n;
    }

    /**
     * Build final regression equation in SPSS-style format: Dependent = β₀ + β₁(Variable Name).
     * Variable names use spaces instead of underscores for display.
     */
    private function buildEquationString(float $intercept, array $coefficients, string $dependentVarName = 'Soil Loss'): string
    {
        $terms = [$dependentVarName . ' = ' . $this->formatNum($intercept)];
        foreach ($coefficients as $name => $coef) {
            $displayName = str_replace('_', ' ', $name);
            $sign = $coef >= 0 ? ' + ' : ' − ';
            $terms[] = $sign . $this->formatNum(abs($coef)) . '(' . $displayName . ')';
        }
        return implode('', $terms);
    }

    private function formatNum(float $n): string
    {
        return number_format($n, 3, '.', ',');
    }

    /**
     * Ridge regression: β = (X'X + λI)^{-1} X'y. Uses all given features.
     * Returns intercept and coefficients only (no inference).
     *
     * @param array<string> $featureNames
     * @param array<array<float>> $X
     * @param array<float> $y
     */
    public function runRidge(array $featureNames, array $X, array $y, float $lambda = 0.001): array
    {
        $n = count($y);
        if ($n < 1 || count($X) !== $n) {
            throw new \InvalidArgumentException('X and y must have the same number of rows.');
        }
        $designMatrix = [];
        foreach ($X as $i => $row) {
            $designMatrix[$i] = array_merge([1.0], array_values($row));
        }
        $Xt = $this->transpose($designMatrix);
        $XtX = $this->matrixMultiply($Xt, $designMatrix);
        $XtXRidge = $this->addDiagonal($XtX, $lambda);
        $XtXInv = $this->inverse($XtXRidge);
        $XtY = $this->matrixVectorMultiply($Xt, $y);
        $beta = $this->matrixVectorMultiply($XtXInv, $XtY);
        $intercept = $beta[0];
        $coefficients = [];
        foreach ($featureNames as $j => $name) {
            $coefficients[$name] = $beta[$j + 1] ?? 0.0;
        }
        return [
            'intercept' => $intercept,
            'coefficients' => $coefficients,
        ];
    }

    /**
     * Bootstrap inference for Ridge: run Ridge B times on resampled data, then compute
     * SE = sd(bootstrap coeffs), t = coef/SE, p = 2*(1-Φ(|t|)) clamped to [0,1].
     * Used only to fill the All Coefficients table for dropped predictors; does not affect official model.
     *
     * @param array<string> $featureNames All 11 predictor names in order
     * @param array<array<float>> $X 2D array [row][col] matching featureNames
     * @param array<float> $y Target
     * @param float $lambda Ridge lambda (e.g. 0.001)
     * @param int $B Number of bootstrap iterations (e.g. 200 or 500)
     * @return array{intercept: float, coefficients: array<string, float>, standard_errors: array<string, float>, t_statistics: array<string, float>, p_values: array<string, float>}
     */
    public function runRidgeBootstrap(array $featureNames, array $X, array $y, float $lambda = 0.001, int $B = 200): array
    {
        $n = count($y);
        if ($n < 2 || count($X) !== $n) {
            throw new \InvalidArgumentException('X and y must have the same length and at least 2 rows.');
        }
        $ridge = $this->runRidge($featureNames, $X, $y, $lambda);
        $interceptMain = $ridge['intercept'];
        $coefMain = $ridge['coefficients'];
        $bootIntercepts = [];
        $bootCoefs = array_fill_keys($featureNames, []);
        $indices = range(0, $n - 1);
        for ($b = 0; $b < $B; $b++) {
            $sampleIdx = [];
            for ($i = 0; $i < $n; $i++) {
                $sampleIdx[] = $indices[random_int(0, $n - 1)];
            }
            $Xb = [];
            $yb = [];
            foreach ($sampleIdx as $i) {
                $Xb[] = $X[$i];
                $yb[] = $y[$i];
            }
            $rb = $this->runRidge($featureNames, $Xb, $yb, $lambda);
            $bootIntercepts[] = $rb['intercept'];
            foreach ($featureNames as $name) {
                $bootCoefs[$name][] = $rb['coefficients'][$name] ?? 0.0;
            }
        }
        $seIntercept = $this->stddev($bootIntercepts);
        $standardErrors = ['intercept' => $seIntercept];
        $tStatistics = ['intercept' => $seIntercept > 1e-10 ? $interceptMain / $seIntercept : 0.0];
        $pValues = ['intercept' => $this->pValueFromT($tStatistics['intercept'], $n - count($featureNames) - 1)];
        foreach ($featureNames as $name) {
            $se = $this->stddev($bootCoefs[$name]);
            $standardErrors[$name] = $se;
            $c = $coefMain[$name] ?? 0.0;
            $tStatistics[$name] = $se > 1e-10 ? $c / $se : 0.0;
            $pValues[$name] = $this->pValueFromT($tStatistics[$name], $n - count($featureNames) - 1);
        }
        return [
            'intercept' => $interceptMain,
            'coefficients' => $coefMain,
            'standard_errors' => $standardErrors,
            't_statistics' => $tStatistics,
            'p_values' => $pValues,
        ];
    }

    private function stddev(array $values): float
    {
        $c = count($values);
        if ($c < 2) {
            return 0.0;
        }
        $mean = array_sum($values) / $c;
        $sumSq = 0.0;
        foreach ($values as $v) {
            $sumSq += ($v - $mean) ** 2;
        }
        return sqrt($sumSq / ($c - 1));
    }

    /** Two-tailed p-value from t, clamped to [0, 1]. Uses normal approximation for bootstrap. */
    private function pValueFromT(float $t, int $df): float
    {
        $absT = abs($t);
        $p = $df >= 30
            ? 2.0 * (1.0 - $this->normalCdf($absT))
            : $this->tCdfTwoTailed($absT, max(1, $df));
        return max(0.0, min(1.0, $p));
    }
}
