@extends('layouts.dashboard')

@section('title', 'Model Builder')

@section('content')
<div class="dashboard-hub" id="modelBuilderPage"
    data-run-regression-url="{{ route('model-builder.run-regression') }}"
    data-saved-equations-index-url="{{ route('saved-equations.index') }}"
    data-saved-equations-store-url="{{ route('saved-equations.store') }}"
    data-saved-equations-base-url="{{ url('saved-equations') }}">
    {{-- Page header with View Saved Equations --}}
    <header class="dashboard-header fade-in-element">
        <div class="header-card mb-header-inner">
            <div class="header-main">
                <div>
                    <h1 class="header-title">
                        <i data-lucide="box" class="lucide-icon lucide-icon-md" aria-hidden="true"></i>
                        Model Builder
                    </h1>
                    <p class="header-location" style="color: rgba(255,255,255,0.9); font-weight: 400;">
                        Build multiple linear regression models from coastal data
                    </p>
                </div>
            </div>
            <a href="{{ route('model-builder.saved-equations') }}" class="mb-btn-view-saved" aria-label="View saved equations page">
                <i data-lucide="bookmark" class="lucide-icon lucide-icon-sm" aria-hidden="true"></i>
                <span>View Saved Equations</span>
            </a>
        </div>
    </header>

    {{-- Input Data --}}
    <section class="dashboard-section model-builder-section fade-in-element" id="modelSelectionInputSection">
        <article class="mb-master-card">
            {{-- Input Data --}}
            <div class="mb-master-section mb-input-section">
                <div class="mb-input-section-header">
                    <h2 class="mb-master-section-title">Input Data</h2>
                </div>
                <p class="mb-input-hint">Fill in the required parameters, use <strong>Load Example</strong> for sample data, or <strong>Clear</strong> to start with an empty table.</p>
                <div class="mb-load-example-row">
                    <button type="button" id="btnLoadExample" class="mb-load-example-btn" aria-label="Load example dataset"
                        title="Load example data into the table.">
                        <i data-lucide="download" class="lucide-icon lucide-icon-sm" aria-hidden="true"></i>
                        Load Example
                    </button>
                    <button type="button" id="btnClearTable" class="mb-clear-table-btn" aria-label="Clear table"
                        title="Clear all cells so you can enter new data.">
                        <i data-lucide="eraser" class="lucide-icon lucide-icon-sm" aria-hidden="true"></i>
                        Clear
                    </button>
                    <span class="mb-load-example-hint">Load example data or clear the table to enter your own.</span>
                </div>
                <div class="mb-table-card mb-input-table-wrap" id="inputTableWrap">
            <div class="mb-table-container">
                <table class="mb-input-table" id="inputTable">
                    <thead>
                        <tr>
                            <th class="col-num" data-th="num">#</th>
                            <th class="col-year" data-th="Year" title="Year of the record (e.g. 2020)">Year</th>
                            <th class="col-storm" data-th="Tropical_Depression" title="Number of tropical depressions">Trop_Depressions</th>
                            <th class="col-storm" data-th="Tropical_Storms" title="Number of tropical storms per period">Trop_Storms</th>
                            <th class="col-storm" data-th="Severe_Tropical_Storms" title="Number of severe tropical storms">Sev_Trop_Storms</th>
                            <th class="col-storm" data-th="Typhoons" title="Number of typhoons">Typhoons</th>
                            <th class="col-storm" data-th="Super_Typhoons" title="Number of super typhoons">Super_Typhoons</th>
                            <th class="col-storm" data-th="Floods" title="Number of flood events">Floods</th>
                            <th class="col-storm" data-th="Storm_Surges" title="Storm surge events">Storm_Surges</th>
                            <th class="col-storm" data-th="Precipitation_mm" title="Total precipitation in mm">Precipitation_mm</th>
                            <th class="col-infra" data-th="Seawall_m" title="Seawall length in meters">Seawall_m</th>
                            <th class="col-infra" data-th="Vegetation_area_sqm" title="Vegetation area in m²">Veg_Area_Sqm</th>
                            <th class="col-infra" data-th="Coastal_Elevation" title="Coastal elevation (m)">Coastal_Elevation</th>
                            <th class="col-target" data-th="Soil_loss_sqm" title="Soil loss (target variable) in m²">Soil_Loss_Sqm <span class="required-star">*</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @for ($i = 1; $i <= 10; $i++)
                        <tr data-row="{{ $i }}">
                            <td class="row-num" data-label="">{{ $i }}</td>
                            <td data-label="Year"><input type="number" name="Year" min="1900" max="2100" step="1" placeholder="2024" class="mb-input mb-input-year" data-col="Year" aria-label="Year for row {{ $i }}" title="Year (1900–2100)"></td>
                            <td data-label="Tropical_Depression"><input type="number" name="Tropical_Depression" min="0" step="0.01" placeholder="0" class="mb-input" data-col="Tropical_Depression" aria-label="Tropical depression"></td>
                            <td data-label="Tropical_Storms"><input type="number" name="Tropical_Storms" min="0" step="0.01" placeholder="0" class="mb-input" data-col="Tropical_Storms" aria-label="Tropical storms"></td>
                            <td data-label="Severe_Tropical_Storms"><input type="number" name="Severe_Tropical_Storms" min="0" step="0.01" placeholder="0" class="mb-input" data-col="Severe_Tropical_Storms"></td>
                            <td data-label="Typhoons"><input type="number" name="Typhoons" min="0" step="0.01" placeholder="0" class="mb-input" data-col="Typhoons"></td>
                            <td data-label="Super_Typhoons"><input type="number" name="Super_Typhoons" min="0" step="0.01" placeholder="0" class="mb-input" data-col="Super_Typhoons"></td>
                            <td data-label="Floods"><input type="number" name="Floods" min="0" step="0.01" placeholder="0" class="mb-input" data-col="Floods"></td>
                            <td data-label="Storm_Surges"><input type="number" name="Storm_Surges" min="0" step="0.01" placeholder="0" class="mb-input" data-col="Storm_Surges"></td>
                            <td data-label="Precipitation_mm"><input type="number" name="Precipitation_mm" min="0" step="0.01" placeholder="0" class="mb-input" data-col="Precipitation_mm"></td>
                            <td data-label="Seawall_m"><input type="number" name="Seawall_m" min="0" step="0.01" placeholder="0" class="mb-input" data-col="Seawall_m"></td>
                            <td data-label="Vegetation_area_sqm"><input type="number" name="Vegetation_area_sqm" min="0" step="0.01" placeholder="0" class="mb-input" data-col="Vegetation_area_sqm"></td>
                            <td data-label="Coastal_Elevation"><input type="number" name="Coastal_Elevation" step="0.01" placeholder="0" class="mb-input" data-col="Coastal_Elevation"></td>
                            <td data-label="Soil_loss_sqm"><input type="number" name="Soil_loss_sqm" min="0" step="0.01" placeholder="0" class="mb-input mb-target" data-col="Soil_loss_sqm" required></td>
                        </tr>
                        @endfor
                    </tbody>
                </table>
            </div>
            </div>
            <div class="mb-run-regression-row">
                <button type="button" id="btnRunRegression" class="btn-run-regression">
                    <i data-lucide="play" class="lucide-icon lucide-icon-md" aria-hidden="true"></i>
                    <span>Run Regression</span>
                </button>
                <div id="regressionError" class="mb-error" role="alert" hidden></div>
                <div id="regressionWarnings" class="mb-warnings" role="status" aria-live="polite" hidden></div>
            </div>
            </div>
        </article>
    </section>

    {{-- Results --}}
    <section class="dashboard-section model-builder-section fade-in-element" id="resultsSection" aria-live="polite">
        <h2 class="section-title"><i data-lucide="bar-chart-2" class="lucide-icon lucide-icon-md" aria-hidden="true"></i> Regression Results</h2>
        <div id="resultsEmpty" class="mb-results-empty" aria-hidden="true"></div>
        <div id="resultsLoading" class="mb-results-loading" hidden aria-hidden="true">
            <span class="mb-spinner"></span>
            <span>Computing regression…</span>
        </div>
        <div id="resultsContent" class="mb-results-content" hidden>
            {{-- Generated Regression Model: dynamic equation from significant predictors only --}}
            <article class="weather-card mb-result-card mb-fade-in mb-generated-model-card" id="generatedModelCard">
                <h2 class="mb-generated-model-title">Generated Regression Model</h2>
                <div class="mb-p-threshold-wrap">
                    <label for="pValueThreshold" class="mb-p-threshold-label">Significance level (p &lt;)</label>
                    <input type="number" id="pValueThreshold" class="mb-p-threshold-input" min="0.01" max="0.5" step="0.01" value="0.05" aria-describedby="pThresholdHint">
                    <span class="mb-p-threshold-hint" id="pThresholdHint">Only predictors with p-value below this threshold appear in the equation (default 0.05).</span>
                </div>
                <div id="generatedModelContent">
                    <div class="mb-equation-header">
                        <h3 class="mb-result-title mb-equation-result-title">Final Equation</h3>
                        <button type="button" class="mb-equation-copy-btn" id="btnCopyEquation" title="Copy equation to clipboard" aria-label="Copy equation to clipboard">
                            <i data-lucide="copy" class="lucide-icon lucide-icon-sm" aria-hidden="true"></i>
                            Copy
                        </button>
                    </div>
                    <details class="mb-equation-details" open>
                        <summary class="mb-equation-summary">Equation</summary>
                        <div class="mb-equation-wrap">
                            <div class="mb-equation-formatted" id="regressionEquationFormatted" aria-label="Regression equation" data-raw-equation="" role="text"></div>
                        </div>
                    </details>
                    <div id="significantPredictorsWrap" class="mb-significant-predictors">
                        <h3 class="mb-result-title">Significant Predictors</h3>
                        <ul id="significantPredictorsList" class="mb-significant-list" aria-label="List of statistically significant predictors"></ul>
                    </div>
                    <div class="mb-model-summary">
                        <h3 class="mb-result-title">Model Summary</h3>
                        <div class="mb-metrics-grid mb-model-summary-grid">
                            <div class="mb-metric">
                                <span class="mb-metric-label">R²</span>
                                <span class="mb-metric-value" id="metricR2">—</span>
                            </div>
                            <div class="mb-metric">
                                <span class="mb-metric-label">Adjusted R²</span>
                                <span class="mb-metric-value" id="metricAdjR2">—</span>
                            </div>
                        </div>
                        <p id="paperStatsNote" class="mb-paper-stats-note" hidden aria-live="polite">Statistical values are based on the regression analysis reported in the research study.</p>
                    </div>
                </div>
                <div id="noSignificantPredictors" class="mb-no-significant" hidden>
                    <p class="mb-no-significant-msg">No statistically significant predictors were identified based on the selected dataset.</p>
                    <p class="mb-no-significant-hint">Try adding more data, using a higher significance level (e.g. 0.10), or checking predictor quality.</p>
                </div>
            </article>
            <article class="weather-card mb-result-card mb-fade-in">
                <h3 class="mb-result-title">All Coefficients</h3>
                <div class="mb-table-scroll mb-coef-table-wrap">
                    <table class="mb-coef-table" id="coefficientsTable">
                        <thead>
                            <tr><th>Variable</th><th>Coefficient</th><th>Std. Error</th><th>t</th><th>p-value</th><th>Significant</th></tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </article>
        </div>
    </section>

    {{-- Model actions - compact horizontal bar --}}
    <section class="dashboard-section model-builder-section fade-in-element" id="modelManagementSection" aria-label="Model actions">
        <div class="mb-management-bar">
            <button type="button" class="mb-management-btn mb-management-primary" id="btnSaveEquation" aria-label="Save equation">
                <i data-lucide="save" class="lucide-icon lucide-icon-sm mb-management-btn-icon" aria-hidden="true"></i>
                <span>Save Equation</span>
            </button>
            <button type="button" class="mb-management-btn mb-management-outline" id="btnRunNew" aria-label="Run new calculation">
                <i data-lucide="refresh-cw" class="lucide-icon lucide-icon-sm mb-management-btn-icon" aria-hidden="true"></i>
                <span>Run New Calculation</span>
            </button>
        </div>
    </section>

    {{-- Save Equation modal --}}
    <div id="saveEquationModal" class="mb-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="saveEquationModalTitle" aria-hidden="true" hidden>
        <div class="mb-modal">
            <div class="mb-modal-header">
                <h2 id="saveEquationModalTitle" class="mb-modal-title">Save Equation</h2>
                <button type="button" class="mb-modal-close" id="saveEquationModalClose" aria-label="Close">&times;</button>
            </div>
            <form id="saveEquationForm" class="mb-modal-body">
                <div class="mb-modal-field">
                    <label for="saveEquationName">Equation Name <span class="required-star">*</span></label>
                    <input type="text" id="saveEquationName" name="equation_name" required maxlength="255" placeholder="e.g. Coastal 2024 Model" autocomplete="off">
                    <span id="saveEquationNameError" class="mb-modal-error" role="alert" hidden></span>
                </div>
                <div class="mb-modal-field">
                    <label for="saveEquationFormula">Formula</label>
                    <textarea id="saveEquationFormula" name="formula" readonly rows="6" class="mb-formula-readonly"></textarea>
                </div>
                <div id="saveEquationFormError" class="mb-modal-error mb-modal-error-block" role="alert" hidden></div>
            </form>
            <div class="mb-modal-footer">
                <button type="button" class="mb-modal-btn mb-modal-btn-cancel" id="saveEquationCancel">Cancel</button>
                <button type="submit" form="saveEquationForm" class="mb-modal-btn mb-modal-btn-primary" id="saveEquationSubmit">Save</button>
            </div>
        </div>
    </div>

    {{-- Edit Equation modal --}}
    <div id="editEquationModal" class="mb-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="editEquationModalTitle" aria-hidden="true" hidden>
        <div class="mb-modal">
            <div class="mb-modal-header">
                <h2 id="editEquationModalTitle" class="mb-modal-title">Edit Equation</h2>
                <button type="button" class="mb-modal-close" id="editEquationModalClose" aria-label="Close">&times;</button>
            </div>
            <form id="editEquationForm" class="mb-modal-body">
                <input type="hidden" id="editEquationId" name="id" value="">
                <div class="mb-modal-field">
                    <label for="editEquationName">Equation Name <span class="required-star">*</span></label>
                    <input type="text" id="editEquationName" name="equation_name" required maxlength="255" placeholder="e.g. Coastal 2024 Model" autocomplete="off">
                    <span id="editEquationNameError" class="mb-modal-error" role="alert" hidden></span>
                </div>
                <div class="mb-modal-field">
                    <label for="editEquationFormula">Formula <span class="required-star">*</span></label>
                    <textarea id="editEquationFormula" name="formula" rows="6" class="mb-formula-editable"></textarea>
                </div>
                <div id="editEquationFormError" class="mb-modal-error mb-modal-error-block" role="alert" hidden></div>
            </form>
            <div class="mb-modal-footer">
                <button type="button" class="mb-modal-btn mb-modal-btn-cancel" id="editEquationCancel">Cancel</button>
                <button type="submit" form="editEquationForm" class="mb-modal-btn mb-modal-btn-primary" id="editEquationSubmit">Update</button>
            </div>
        </div>
    </div>

    {{-- Delete Equation confirmation modal --}}
    <div id="deleteEquationModal" class="mb-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="deleteEquationModalTitle" aria-hidden="true" hidden>
        <div class="mb-modal mb-modal-sm">
            <div class="mb-modal-header">
                <h2 id="deleteEquationModalTitle" class="mb-modal-title">Delete Equation</h2>
                <button type="button" class="mb-modal-close" id="deleteEquationModalClose" aria-label="Close">&times;</button>
            </div>
            <div class="mb-modal-body">
                <p id="deleteEquationMessage" class="mb-delete-message">Are you sure you want to delete this equation? This cannot be undone.</p>
            </div>
            <div class="mb-modal-footer">
                <button type="button" class="mb-modal-btn mb-modal-btn-cancel" id="deleteEquationCancel">Cancel</button>
                <button type="button" class="mb-modal-btn mb-modal-btn-danger" id="deleteEquationConfirm">Delete</button>
            </div>
        </div>
    </div>
</div>

@push('styles')
    @vite(['resources/views/user/css/model-builder.css'])
    @vite(['resources/views/user/css/saved-equations.css'])
@endpush

@push('scripts')
    @vite(['resources/views/user/js/model-builder.js'])
@endpush
@endsection
