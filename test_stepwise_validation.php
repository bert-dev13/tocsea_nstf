<?php

/**
 * Validation test: Stepwise (SPSS-style) on the user-provided dataset.
 * Uses SPSS defaults: Probability of F to Enter = 0.05, to Remove = 0.10.
 * With 0.05, both Tropical_Depression and Tropical_Storms may enter (Tropical_Storms p ≈ 0.037).
 *
 * Run from project root: php test_stepwise_validation.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\RegressionService;

// Validation dataset (from user prompt). CSV columns map to our feature names.
$rows = [
    ['Year' => 2018, 'Trop_Depressions' => 4, 'Trop_Storms' => 3, 'Sev_Trop_Storms' => 1, 'Typhoons' => 1, 'Super_Typhoons' => 0, 'Floods' => 2, 'Storm_Surges' => 0, 'Precipitation_mm' => 2365, 'Seawall_m' => 1537, 'Veg_Area_Sqm' => 1828043, 'Coastal_Elevation' => 2.06, 'Soil_Loss_Sqm' => 80045.17],
    ['Year' => 2017, 'Trop_Depressions' => 1, 'Trop_Storms' => 2, 'Sev_Trop_Storms' => 2, 'Typhoons' => 2, 'Super_Typhoons' => 0, 'Floods' => 3, 'Storm_Surges' => 1, 'Precipitation_mm' => 2286, 'Seawall_m' => 1471, 'Veg_Area_Sqm' => 1863028, 'Coastal_Elevation' => 1.93, 'Soil_Loss_Sqm' => 87493.48],
    ['Year' => 2016, 'Trop_Depressions' => 3, 'Trop_Storms' => 1, 'Sev_Trop_Storms' => 1, 'Typhoons' => 1, 'Super_Typhoons' => 1, 'Floods' => 2, 'Storm_Surges' => 2, 'Precipitation_mm' => 1727, 'Seawall_m' => 1105, 'Veg_Area_Sqm' => 1801427, 'Coastal_Elevation' => 2.17, 'Soil_Loss_Sqm' => 73378.48],
    ['Year' => 2015, 'Trop_Depressions' => 2, 'Trop_Storms' => 1, 'Sev_Trop_Storms' => 1, 'Typhoons' => 1, 'Super_Typhoons' => 1, 'Floods' => 3, 'Storm_Surges' => 1, 'Precipitation_mm' => 1608, 'Seawall_m' => 1121, 'Veg_Area_Sqm' => 1837823, 'Coastal_Elevation' => 2.19, 'Soil_Loss_Sqm' => 80439.57],
    ['Year' => 2014, 'Trop_Depressions' => 1, 'Trop_Storms' => 0, 'Sev_Trop_Storms' => 1, 'Typhoons' => 2, 'Super_Typhoons' => 1, 'Floods' => 3, 'Storm_Surges' => 1, 'Precipitation_mm' => 2293, 'Seawall_m' => 672, 'Veg_Area_Sqm' => 1801065, 'Coastal_Elevation' => 2.09, 'Soil_Loss_Sqm' => 92464.67],
    ['Year' => 2013, 'Trop_Depressions' => 3, 'Trop_Storms' => 0, 'Sev_Trop_Storms' => 2, 'Typhoons' => 1, 'Super_Typhoons' => 0, 'Floods' => 3, 'Storm_Surges' => 0, 'Precipitation_mm' => 1589, 'Seawall_m' => 446, 'Veg_Area_Sqm' => 1812619, 'Coastal_Elevation' => 2.17, 'Soil_Loss_Sqm' => 68236.59],
    ['Year' => 2012, 'Trop_Depressions' => 1, 'Trop_Storms' => 2, 'Sev_Trop_Storms' => 1, 'Typhoons' => 1, 'Super_Typhoons' => 0, 'Floods' => 3, 'Storm_Surges' => 1, 'Precipitation_mm' => 2457, 'Seawall_m' => 154, 'Veg_Area_Sqm' => 1870783, 'Coastal_Elevation' => 2.04, 'Soil_Loss_Sqm' => 114889.73],
    ['Year' => 2011, 'Trop_Depressions' => 0, 'Trop_Storms' => 4, 'Sev_Trop_Storms' => 1, 'Typhoons' => 2, 'Super_Typhoons' => 0, 'Floods' => 3, 'Storm_Surges' => 2, 'Precipitation_mm' => 2186, 'Seawall_m' => 0, 'Veg_Area_Sqm' => 1918874, 'Coastal_Elevation' => 1.94, 'Soil_Loss_Sqm' => 120000],
    ['Year' => 2010, 'Trop_Depressions' => 2, 'Trop_Storms' => 3, 'Sev_Trop_Storms' => 2, 'Typhoons' => 1, 'Super_Typhoons' => 1, 'Floods' => 2, 'Storm_Surges' => 0, 'Precipitation_mm' => 2073, 'Seawall_m' => 273, 'Veg_Area_Sqm' => 1966548, 'Coastal_Elevation' => 2.19, 'Soil_Loss_Sqm' => 90339.54],
    ['Year' => 2009, 'Trop_Depressions' => 3, 'Trop_Storms' => 3, 'Sev_Trop_Storms' => 1, 'Typhoons' => 1, 'Super_Typhoons' => 1, 'Floods' => 2, 'Storm_Surges' => 1, 'Precipitation_mm' => 2246, 'Seawall_m' => 0, 'Veg_Area_Sqm' => 1901210, 'Coastal_Elevation' => 2.17, 'Soil_Loss_Sqm' => 82048.97],
];

$featureNames = [
    'Tropical_Depression', 'Tropical_Storms', 'Severe_Tropical_Storms', 'Typhoons', 'Super_Typhoons',
    'Floods', 'Storm_Surges', 'Precipitation_mm', 'Seawall_m', 'Vegetation_area_sqm', 'Coastal_Elevation',
];

$featureToCsv = [
    'Tropical_Depression' => 'Trop_Depressions',
    'Tropical_Storms' => 'Trop_Storms',
    'Severe_Tropical_Storms' => 'Sev_Trop_Storms',
    'Typhoons' => 'Typhoons',
    'Super_Typhoons' => 'Super_Typhoons',
    'Floods' => 'Floods',
    'Storm_Surges' => 'Storm_Surges',
    'Precipitation_mm' => 'Precipitation_mm',
    'Seawall_m' => 'Seawall_m',
    'Vegetation_area_sqm' => 'Veg_Area_Sqm',
    'Coastal_Elevation' => 'Coastal_Elevation',
];

$X = [];
$y = [];
foreach ($rows as $row) {
    $xRow = [];
    foreach ($featureNames as $f) {
        $csvKey = $featureToCsv[$f] ?? $f;
        $xRow[$f] = (float) ($row[$csvKey] ?? 0);
    }
    $X[] = $xRow;
    $y[] = (float) $row['Soil_Loss_Sqm'];
}

$service = new RegressionService();

// Single-predictor model (Tropical_Depression only) - expected SPSS result
$onePred = ['Tropical_Depression'];
$XOne = array_map(fn ($row) => ['Tropical_Depression' => $row['Tropical_Depression']], $X);
$fitOne = $service->runOlsStrict($onePred, $XOne, $y);
echo "Single-predictor model (Tropical_Depression only) - SPSS expected:\n";
echo "  R²: " . $fitOne['r_squared'] . ", Adj R²: " . $fitOne['adjusted_r_squared'] . "\n";
echo "  Equation: " . $fitOne['equation'] . "\n\n";

$result = $service->runStepwise($featureNames, $X, $y, \App\Services\RegressionService::STEPWISE_ENTRY_P, \App\Services\RegressionService::STEPWISE_REMOVAL_P);

echo "Stepwise (SPSS-style) validation test\n";
echo "-------------------------------------\n";
echo "R²: " . $result['r_squared'] . "\n";
echo "Adjusted R²: " . $result['adjusted_r_squared'] . "\n";
echo "Selected predictors: " . implode(', ', $result['selected_predictors'] ?? []) . "\n";
echo "Equation: " . $result['equation'] . "\n";
echo "Std. Error of Estimate: " . ($result['standard_error_of_estimate'] ?? '—') . "\n";
if (!empty($result['anova'])) {
    echo "ANOVA F: " . $result['anova']['F'] . ", p: " . $result['anova']['p_value'] . "\n";
}
if (!empty($result['predicted_values'])) {
    echo "Predicted values / residuals: present (" . count($result['predicted_values']) . " rows)\n";
}

// Validation: Stepwise must retain only Tropical_Depression for this dataset (entry 0.03).
$selected = $result['selected_predictors'] ?? [];
$r2 = $result['r_squared'];
$adjR2 = $result['adjusted_r_squared'];

$ok = true;
if (count($selected) !== 1 || $selected[0] !== 'Tropical_Depression') {
    echo "FAIL: Stepwise must retain only Tropical_Depression. Got: " . json_encode($selected) . "\n";
    $ok = false;
}
if ($r2 < 0 || $r2 > 1) {
    echo "FAIL: R² = $r2 must be in [0, 1].\n";
    $ok = false;
}
if ($adjR2 < 0 || $adjR2 > 1) {
    echo "FAIL: Adjusted R² = $adjR2 must be in [0, 1].\n";
    $ok = false;
}
if (empty($result['equation']) || strpos($result['equation'], 'Soil Loss') !== 0) {
    echo "FAIL: Equation must start with 'Soil Loss = '.\n";
    $ok = false;
}
if (!isset($result['anova']['ss_regression']) || !isset($result['anova']['ss_residual'])) {
    echo "FAIL: ANOVA must include ss_regression and ss_residual.\n";
    $ok = false;
}
if (!isset($result['step_log']) || !is_array($result['step_log'])) {
    echo "FAIL: Result must include step_log array.\n";
    $ok = false;
}
if (!isset($result['validation']['correlations']) || !isset($result['validation']['simple_regressions'])) {
    echo "FAIL: Result must include validation.correlations and validation.simple_regressions.\n";
    $ok = false;
}

if ($ok) {
    echo "\nValidation PASSED: Stepwise (SPSS default 0.05/0.10), structure and validation data OK.\n";
    exit(0);
}

echo "\nValidation FAILED.\n";
exit(1);
