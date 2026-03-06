# How TOCSEA Generates Results

This document explains how the system produces **regression models** (Model Builder) and **soil loss predictions** (Soil Calculator), with emphasis on **input columns**, **formulas**, and **technical terms**.

---

## Technical terms (glossary)

| Term | Meaning |
|------|--------|
| **Predictor** | An input variable (e.g. Seawall_m, Typhoons) used to predict the target. Also called *independent variable* or *feature*. |
| **Target** | The variable you want to predict (here: **Soil_loss_sqm**). Also called *dependent variable* or *response*. |
| **Intercept** | The constant term in the equation (β₀). Predicted value when all predictors are zero. |
| **Coefficient (β)** | The weight for one predictor. How much the target changes per unit change in that predictor. |
| **OLS (Ordinary Least Squares)** | Method that finds coefficients by minimizing the sum of squared differences between observed and predicted values. |
| **Design matrix (X)** | Table of predictor values: one row per observation, one column per predictor, often with a column of 1s for the intercept. |
| **R² (coefficient of determination)** | Fraction of variation in the target explained by the model (0 to 1). R² = 1 − (SS_residual / SS_total). |
| **Adjusted R²** | R² adjusted for number of predictors; penalizes adding predictors that don’t help. |
| **Standard error (SE)** | Uncertainty of a coefficient estimate; used to compute t and p-value. |
| **t-statistic** | Coefficient ÷ its standard error. Measures how far the estimate is from zero in “SE units”. |
| **p-value** | Probability of seeing such an extreme result if the true coefficient were zero. Low p (e.g. &lt; 0.05) suggests the predictor is *significant*. |
| **Significance level (α)** | Threshold for p-value (e.g. 0.05). Predictors with p &lt; α are called *statistically significant*. |
| **Residual** | For one row: observed target minus predicted target (y − ŷ). |
| **RMSE** | Root mean squared error: square root of the average of squared residuals. |
| **MAE** | Mean absolute error: average of absolute residuals. |

---

## Input table: the columns

The Model Builder **Input Data** table has **14 columns** (including row number). Of these, **11 are predictors** and **1 is the target**; **Year** is for reference only and is **not** used as a predictor in the regression.

### Predictor columns (11)

These are the **X** variables the system uses to fit the model. Only columns that have at least one numeric value in your data are included.

| # | Column name (internal) | Short label in UI | Description |
|---|-------------------------|-------------------|-------------|
| 1 | **Tropical_Depression** | Trop_Depressions | Number of tropical depressions |
| 2 | **Tropical_Storms** | Trop_Storms | Number of tropical storms per period |
| 3 | **Severe_Tropical_Storms** | Sev_Trop_Storms | Number of severe tropical storms |
| 4 | **Typhoons** | Typhoons | Number of typhoons |
| 5 | **Super_Typhoons** | Super_Typhoons | Number of super typhoons |
| 6 | **Floods** | Floods | Number of flood events |
| 7 | **Storm_Surges** | Storm_Surges | Number of storm surge events |
| 8 | **Precipitation_mm** | Precipitation_mm | Total precipitation (mm) |
| 9 | **Seawall_m** | Seawall_m | Seawall length (meters) |
| 10 | **Vegetation_area_sqm** | Veg_Area_Sqm | Vegetation area (m²) |
| 11 | **Coastal_Elevation** | Coastal_Elevation | Coastal elevation (m) |

### Other columns

| Column | Role |
|--------|------|
| **Year** | Shown in the table for context; **not** used as a predictor in the regression. |
| **Soil_loss_sqm** | **Target (y)**. Required; at least 10 rows must have a valid numeric value. Soil loss in m². |

So in formulas below, **X** refers to the 11 predictor columns (only those with data), and **y** is **Soil_loss_sqm**.

---

## 1. Model Builder: how the regression result is generated

### What you do

1. Fill the **Input Data** table: one row per observation (e.g. per year or site). Use the **11 predictor columns** and the **Soil_loss_sqm** column; **Year** is optional for your reference.
2. Click **Run Regression**.

### Formulas used by the system

**1. Regression model (what we assume)**

The system fits a **multiple linear regression**:

- **Symbolic form:**  
  **y = β₀ + β₁·x₁ + β₂·x₂ + … + βₚ·xₚ + ε**

  Where:
  - **y** = Soil_loss_sqm (target)
  - **x₁, x₂, …, xₚ** = the predictor variables (e.g. Seawall_m, Typhoons, …)
  - **β₀** = intercept (constant)
  - **β₁, β₂, …, βₚ** = coefficients (one per predictor)
  - **ε** = error (residual)

- **Vector/matrix form:**  
  **y = Xβ + ε**

  - **y** = column of observed soil loss values (one per row).
  - **X** = **design matrix**: one column of 1s (for the intercept) plus one column per predictor used. Each row is one observation.
  - **β** = column of coefficients (intercept first, then one per predictor).

**2. How coefficients are computed (OLS)**

The system estimates **β** using **Ordinary Least Squares**:

- **Formula:**  
  **β = (X′X)⁻¹ X′y**

  - **X′** = transpose of X  
  - **(X′X)⁻¹** = inverse of the matrix X′X  
  - So: intercept and each coefficient come from this single matrix equation.

**3. Fitted values and R²**

- **Fitted (predicted) values:**  
  **ŷ = Xβ**

- **R² (coefficient of determination):**  
  **R² = 1 − (SS_res / SS_tot)**  
  where  
  - SS_res = Σ (y − ŷ)² (residual sum of squares)  
  - SS_tot = Σ (y − ȳ)² (total sum of squares), ȳ = mean of y  

- **Adjusted R²:**  
  **Adjusted R² = 1 − (1 − R²)·(n − 1)/(n − p)**  
  where n = number of observations, p = number of parameters (intercept + predictors).

**4. Standard errors, t-statistics, and p-values**

- **Variance of residuals:** σ² = SS_res / (n − p) (estimated residual variance).
- **Standard error of coefficient βⱼ:**  
  **SE(βⱼ) = √(σ² · (X′X)⁻¹_{jj})**  
  (square root of the j-th diagonal entry of σ²·(X′X)⁻¹.)

- **t-statistic for coefficient j:**  
  **t = βⱼ / SE(βⱼ)**

- **p-value:** Two-tailed p-value for that t-statistic (probability of seeing such |t| if true coefficient were 0). Used to decide if a predictor is **statistically significant** (e.g. p &lt; 0.05).

**5. Equation shown on the page**

The **Generated Regression Model** displays an equation of the form:

- **Soil_loss_sqm = β₀ + (β₁ × Variable₁) + (β₂ × Variable₂) + …**

Only predictors with **p-value &lt; significance level** (default 0.05) are included. Example (with numbers):

- **Soil_loss_sqm = 81,610.062 − (54.458 × Seawall_m) + (12.340 × Precipitation_mm) + …**

So: **result = intercept + sum over predictors of (coefficient × value)**.

### Flow summary

1. Table data → validation (≥10 rows with valid **Soil_loss_sqm**, only predictors with data used).
2. Build **X** (design matrix) and **y** (target column).
3. Compute **β = (X′X)⁻¹ X′y** (OLS).
4. Compute **ŷ = Xβ**, then **R²**, **Adjusted R²**, **SE**, **t**, **p-values**.
5. Build equation string: **Soil_loss_sqm = intercept + (coef × name)** for each predictor; UI filters by p-value.
6. You can **Save Equation**; that stored formula is used later in the Soil Calculator.

---

## 2. Soil Calculator: how the prediction result is generated

The Soil Calculator does **not** run regression again. It **evaluates a linear equation** using the coefficients from Model Builder (or the fixed Buguey model).

### Prediction formula (generic)

For any linear equation with intercept and coefficients:

- **Predicted soil loss = β₀ + β₁·x₁ + β₂·x₂ + … + βₚ·xₚ**

Same as: **intercept + Σ (coefficient × input value)** for each variable in the equation.

### Default (Buguey) model

Fixed formula in code (same structure as above):

- **Predicted soil loss = 49,218.016 − (61.646 × seawall) + (19.931 × precipitation) + (1,779.250 × tropical_storm) + (2,389.243 × flood)**

So the **result** is this single number (m²/year), then risk level (Low/Moderate/High) is derived from thresholds applied to that value.

### Saved equation from Model Builder

1. The saved equation is **text**, e.g.:  
   `Soil Loss = 81,610.062`  
   `− (54.458 × Seawall_m)`  
   `+ (12.340 × Precipitation_mm)`  
   `+ …`

2. The Soil Calculator **parses** this to get:
   - **Intercept** = number after the first `=`
   - **Terms** = each line of the form ± (number × Variable_name)

3. It reads from the form the **numeric value** for each variable name (e.g. Seawall_m, Precipitation_mm).

4. It **evaluates** the same formula:  
   **predicted soil loss = intercept + Σ (coefficient × value)**  
   for every term in the equation.

5. That number is the **result** (m²/year); risk level and other labels are then computed from it and your inputs.

---

## 3. Calculation History

Each Soil Calculator run stores:

- **Equation name** (e.g. “Buguey” or the saved equation name)
- **Formula snapshot** (the exact equation string used)
- **Inputs** (values entered for each variable)
- **Result** (the single predicted soil loss number from **intercept + Σ (coefficient × value)**)
- **Saved equation ID** (if a Model Builder equation was used)

No new regression is run; the stored result is the same number that was computed by evaluating the chosen equation.

---

## Summary table

| Part of the system | Columns / inputs | Main formulas | Result |
|--------------------|------------------|---------------|--------|
| **Model Builder** | 11 predictor columns + **Soil_loss_sqm** (target); Year optional | **β = (X′X)⁻¹ X′y**; **ŷ = Xβ**; **R² = 1 − SS_res/SS_tot**; **SE**, **t**, **p-value** per coefficient | Equation: **Soil_loss_sqm = β₀ + Σ (βⱼ × xⱼ)** + statistics |
| **Soil Calculator** | User inputs for each variable in the equation | **Predicted soil loss = intercept + Σ (coefficient × value)** | One number (m²/year) + risk level |
| **Calculation History** | Stored inputs + equation snapshot | (none; stores the result from the equation above) | Stored equation, inputs, and result number |

---

## Code references

- **Regression (OLS, R², SE, t, p):** `App\Services\RegressionService`
- **Model Builder API:** `POST /model-builder/run-regression` — builds X and y from the 11 predictor columns + **Soil_loss_sqm**, calls `RegressionService`, returns intercept, coefficients, equation, and statistics.
- **Soil Calculator:** `parseSavedFormula(formulaStr)` and `evaluateSavedFormula(parsed, values)` in `soil-calculator.js`; built-in Buguey formula in `PREDICTION_MODELS.buguey`.
