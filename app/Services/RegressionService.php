<?php

namespace App\Services;

/**
 * Multiple Linear Regression using Ordinary Least Squares (OLS).
 * Solves: y = Xβ + ε where β = (X'X)^{-1} X'y
 * Uses pseudo-inverse / Ridge fallback when X'X is singular.
 * Computes standard errors, t-statistics, p-values, and adjusted R².
 */
class RegressionService
{
    private const RIDGE_LAMBDA = 1e-8;

    /**
     * Run multiple linear regression on the given data.
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
     *   ridge_used?: bool
     * }
     */
    public function run(array $featureNames, array $X, array $y): array
    {
        $n = count($y);
        if ($n < 2 || count($X) !== $n) {
            throw new \InvalidArgumentException('X and y must have the same number of rows and at least 2 observations.');
        }

        // Build design matrix: add column of 1s for intercept
        $designMatrix = [];
        foreach ($X as $i => $row) {
            $designMatrix[$i] = array_merge([1.0], array_values($row));
        }
        $p = count($designMatrix[0]); // number of parameters (incl. intercept)
        $dfResidual = $n - $p;

        $Xt = $this->transpose($designMatrix);
        $XtX = $this->matrixMultiply($Xt, $designMatrix);
        $XtY = $this->matrixVectorMultiply($Xt, $y);

        $XtXInv = null;
        $ridgeUsed = false;
        try {
            $XtXInv = $this->inverse($XtX);
        } catch (\RuntimeException $e) {
            // Singular: use Ridge fallback (X'X + λI)^{-1} X'y
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
        $rSquared = $this->rSquared($y, $yPred);
        $adjustedRSquared = $this->adjustedRSquared($rSquared, $n, $p);
        $rmse = $this->rmse($y, $yPred);
        $mae = $this->mae($y, $yPred);

        // Residual sum of squares and sigma^2 for standard errors
        $ssRes = 0.0;
        foreach ($y as $i => $yi) {
            $ssRes += ($yi - $yPred[$i]) ** 2;
        }
        $sigmaSq = $dfResidual > 0 ? $ssRes / $dfResidual : 0.0;

        // Standard errors: SE(beta_j) = sqrt(sigma^2 * (X'X)^{-1}_{jj})
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

        $equation = $this->buildEquationString($intercept, $coefficients);

        $result = [
            'intercept' => $intercept,
            'coefficients' => $coefficients,
            'standard_errors' => array_map(fn ($v) => round($v, 6), $standardErrors),
            't_statistics' => array_map(fn ($v) => round($v, 4), $tStatistics),
            'p_values' => array_map(fn ($v) => round($v, 6), $pValues),
            'r_squared' => round($rSquared, 6),
            'adjusted_r_squared' => round($adjustedRSquared, 6),
            'rmse' => round($rmse, 4),
            'mae' => round($mae, 4),
            'equation' => $equation,
            'df_residual' => $dfResidual,
        ];
        if ($ridgeUsed) {
            $result['ridge_used'] = true;
        }
        return $result;
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
                $sum = 0;
                for ($k = 0; $k < $colsA; $k++) {
                    $sum += $A[$i][$k] * $B[$k][$j];
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
            $sum = 0;
            foreach ($row as $j => $val) {
                $sum += $val * $v[$j];
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

    private function buildEquationString(float $intercept, array $coefficients): string
    {
        $terms = ['Soil_loss_sqm = ' . $this->formatNum($intercept)];
        foreach ($coefficients as $name => $coef) {
            $sign = $coef >= 0 ? ' + ' : ' − ';
            $terms[] = $sign . $this->formatNum(abs($coef)) . ' × ' . $name;
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
