<?php

namespace App\Services;

class OlsRegression
{
    /** @var array<int, array<int, float>> */
    private array $X;

    /** @var array<int, float> */
    private array $y;

    /** @var array<int, string>|null */
    private ?array $featureNames = null;

    /** @var array<string, float> */
    private array $coefficients = [];

    private float $intercept = 0.0;

    /** @var array<int, float> */
    private array $predictions = [];

    private float $rSquared = 0.0;

    private float $rmse = 0.0;

    private float $mae = 0.0;

    /**
     * Fit the OLS model: β = (X'X)^{-1} X'y
     *
     * @param array<int, array<int|string, float>> $X 2D array of features [row => [col => value]]
     * @param array<int, float> $y 1D array of target values
     * @param array<int, string>|null $featureNames Optional names for each feature column (associative keys)
     * @return array{intercept: float, coefficients: array<string, float>, r_squared: float, rmse: float, mae: float, equation_string: string}
     */
    public function fit(array $X, array $y, ?array $featureNames = null): array
    {
        $this->X = array_values($X);
        $this->y = array_values($y);
        $this->featureNames = $featureNames;

        $n = count($this->X);
        $m = count($this->X[0] ?? []);

        if ($n === 0 || $m === 0) {
            throw new \InvalidArgumentException('X must be a non-empty 2D array');
        }
        if (count($this->y) !== $n) {
            throw new \InvalidArgumentException('y length must match X row count');
        }

        // Normalize X to numeric 2D array
        $XNum = [];
        foreach ($this->X as $row) {
            $XNum[] = array_values(array_map('floatval', $row));
        }

        // Prepend intercept column (1s)
        $XWithIntercept = [];
        foreach ($XNum as $row) {
            $XWithIntercept[] = array_merge([1.0], $row);
        }

        // X' (transpose)
        $Xt = $this->transpose($XWithIntercept);

        // X'X
        $XtX = $this->multiply($Xt, $XWithIntercept);

        // (X'X)^{-1}
        $XtXInv = $this->inverse($XtX);

        // X'y
        $XtY = $this->multiplyVector($Xt, $this->y);

        // β = (X'X)^{-1} X'y
        $beta = $this->multiplyVector($XtXInv, $XtY);

        $this->intercept = $beta[0];

        for ($i = 1; $i < count($beta); $i++) {
            $name = ($this->featureNames !== null && isset($this->featureNames[$i - 1]))
                ? $this->featureNames[$i - 1]
                : 'x' . $i;
            $this->coefficients[$name] = $beta[$i];
        }

        // Predictions: ŷ = Xβ (using X with intercept)
        $this->predictions = $this->multiplyVector($XWithIntercept, $beta);

        // R² = 1 - SS_res / SS_tot
        $meanY = array_sum($this->y) / $n;
        $ssTot = 0.0;
        $ssRes = 0.0;
        $sumSqErr = 0.0;
        $sumAbsErr = 0.0;

        for ($i = 0; $i < $n; $i++) {
            $ssTot += ($this->y[$i] - $meanY) ** 2;
            $err = $this->y[$i] - $this->predictions[$i];
            $ssRes += $err ** 2;
            $sumSqErr += $err ** 2;
            $sumAbsErr += abs($err);
        }

        $this->rSquared = $ssTot > 0 ? (1.0 - $ssRes / $ssTot) : 0.0;
        $this->rmse = $n > 0 ? sqrt($sumSqErr / $n) : 0.0;
        $this->mae = $n > 0 ? ($sumAbsErr / $n) : 0.0;

        return $this->toArray();
    }

    /**
     * Matrix transpose: A'[j][i] = A[i][j]
     *
     * @param array<int, array<int, float>> $A
     * @return array<int, array<int, float>>
     */
    private function transpose(array $A): array
    {
        $rows = count($A);
        $cols = count($A[0] ?? []);

        $result = [];
        for ($j = 0; $j < $cols; $j++) {
            $result[$j] = [];
            for ($i = 0; $i < $rows; $i++) {
                $result[$j][$i] = $A[$i][$j];
            }
        }
        return $result;
    }

    /**
     * Matrix multiply: C = A * B
     *
     * @param array<int, array<int, float>> $A
     * @param array<int, array<int, float>> $B
     * @return array<int, array<int, float>>
     */
    private function multiply(array $A, array $B): array
    {
        $rowsA = count($A);
        $colsA = count($A[0] ?? []);
        $colsB = count($B[0] ?? []);

        if ($colsA !== count($B)) {
            throw new \InvalidArgumentException('Matrix dimensions incompatible for multiplication');
        }

        $result = [];
        for ($i = 0; $i < $rowsA; $i++) {
            $result[$i] = [];
            for ($j = 0; $j < $colsB; $j++) {
                $sum = 0.0;
                for ($k = 0; $k < $colsA; $k++) {
                    $sum += $A[$i][$k] * $B[$k][$j];
                }
                $result[$i][$j] = $sum;
            }
        }
        return $result;
    }

    /**
     * Matrix * vector: result[i] = sum_k A[i][k] * v[k]
     *
     * @param array<int, array<int, float>> $A
     * @param array<int, float> $v
     * @return array<int, float>
     */
    private function multiplyVector(array $A, array $v): array
    {
        $rows = count($A);
        $cols = count($A[0] ?? []);

        if ($cols !== count($v)) {
            throw new \InvalidArgumentException('Matrix columns must match vector length');
        }

        $result = [];
        for ($i = 0; $i < $rows; $i++) {
            $sum = 0.0;
            for ($k = 0; $k < $cols; $k++) {
                $sum += $A[$i][$k] * $v[$k];
            }
            $result[$i] = $sum;
        }
        return $result;
    }

    /**
     * Matrix inverse using Gauss-Jordan elimination
     *
     * @param array<int, array<int, float>> $A Square matrix
     * @return array<int, array<int, float>>
     */
    private function inverse(array $A): array
    {
        $n = count($A);
        if ($n === 0 || count($A[0] ?? []) !== $n) {
            throw new \InvalidArgumentException('Inverse requires a square matrix');
        }

        $aug = [];
        for ($i = 0; $i < $n; $i++) {
            $aug[$i] = array_merge($A[$i], array_fill(0, $n, 0));
            $aug[$i][$n + $i] = 1;
        }

        // Forward elimination
        for ($col = 0; $col < $n; $col++) {
            $maxRow = $col;
            for ($row = $col + 1; $row < $n; $row++) {
                if (abs($aug[$row][$col]) > abs($aug[$maxRow][$col])) {
                    $maxRow = $row;
                }
            }
            $temp = $aug[$col];
            $aug[$col] = $aug[$maxRow];
            $aug[$maxRow] = $temp;

            $pivot = $aug[$col][$col];
            if (abs($pivot) < 1e-10) {
                throw new \RuntimeException('Matrix is singular and cannot be inverted');
            }
            for ($j = 0; $j < 2 * $n; $j++) {
                $aug[$col][$j] /= $pivot;
            }

            for ($row = 0; $row < $n; $row++) {
                if ($row !== $col && abs($aug[$row][$col]) > 1e-10) {
                    $factor = $aug[$row][$col];
                    for ($j = 0; $j < 2 * $n; $j++) {
                        $aug[$row][$j] -= $factor * $aug[$col][$j];
                    }
                }
            }
        }

        $inv = [];
        for ($i = 0; $i < $n; $i++) {
            $inv[$i] = array_slice($aug[$i], $n);
        }
        return $inv;
    }

    /**
     * Build equation string: y = β0 + β1*x1 + β2*x2 + ...
     */
    private function buildEquationString(): string
    {
        $parts = [sprintf('%.6g', $this->intercept)];
        foreach ($this->coefficients as $name => $coef) {
            $sign = $coef >= 0 ? '+' : '';
            $parts[] = $sign . sprintf('%.6g', $coef) . '*' . $name;
        }
        return 'y = ' . implode(' ', $parts);
    }

    /**
     * @return array{intercept: float, coefficients: array<string, float>, r_squared: float, rmse: float, mae: float, equation_string: string}
     */
    public function toArray(): array
    {
        return [
            'intercept' => $this->intercept,
            'coefficients' => $this->coefficients,
            'r_squared' => $this->rSquared,
            'rmse' => $this->rmse,
            'mae' => $this->mae,
            'equation_string' => $this->buildEquationString(),
        ];
    }

    /**
     * Predict for new X (without intercept column)
     *
     * @param array<int, array<int|string, float>> $X
     * @return array<int, float>
     */
    public function predict(array $X): array
    {
        $coefList = array_values($this->coefficients);
        $predictions = [];
        foreach ($X as $row) {
            $row = array_values(array_map('floatval', $row));
            $val = $this->intercept;
            for ($i = 0; $i < count($coefList); $i++) {
                $val += ($row[$i] ?? 0) * $coefList[$i];
            }
            $predictions[] = $val;
        }
        return $predictions;
    }
}
