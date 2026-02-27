<?php

require __DIR__ . '/vendor/autoload.php';

use App\Services\OlsRegression;

// Simple example: y = 1 + 2*x1 + 3*x2 (with some noise)
$X = [
    [1, 2],
    [2, 3],
    [3, 4],
    [4, 5],
    [5, 6],
];
$y = [1 + 2*1 + 3*2, 1 + 2*2 + 3*3, 1 + 2*3 + 3*4, 1 + 2*4 + 3*5, 1 + 2*5 + 3*6];

$ols = new OlsRegression();
$result = $ols->fit($X, $y, ['rainfall', 'slope']);

echo "Intercept: " . $result['intercept'] . "\n";
echo "Coefficients: " . json_encode($result['coefficients'], JSON_PRETTY_PRINT) . "\n";
echo "R²: " . $result['r_squared'] . "\n";
echo "RMSE: " . $result['rmse'] . "\n";
echo "MAE: " . $result['mae'] . "\n";
echo "Equation: " . $result['equation_string'] . "\n";
