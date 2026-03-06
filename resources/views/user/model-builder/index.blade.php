@extends('layouts.dashboard')

@section('title', 'Model Builder')

@section('content')
<div class="dashboard-hub mb-page" id="modelBuilderPage"
    data-run-regression-url="{{ route('model-builder.run-regression') }}"
    data-saved-equations-index-url="{{ route('saved-equations.index') }}"
    data-saved-equations-store-url="{{ route('saved-equations.store') }}"
    data-saved-equations-base-url="{{ url('saved-equations') }}">
    {{-- Page header --}}
    <header class="dashboard-header fade-in-element mb-page-header">
        <div class="header-card mb-header-inner">
            <div class="header-main">
                <div>
                    <h1 class="header-title">
                        <i data-lucide="box" class="lucide-icon lucide-icon-md" aria-hidden="true"></i>
                        Model Builder
                    </h1>
                    <p class="header-location mb-page-desc">
                        Build multiple linear regression models from coastal data. OLS-based engine aligned with SPSS for Stepwise and Enter methods.
                    </p>
                </div>
            </div>
            <a href="{{ route('model-builder.saved-equations') }}" class="mb-btn-view-saved" aria-label="View saved equations page">
                <i data-lucide="bookmark" class="lucide-icon lucide-icon-sm" aria-hidden="true"></i>
                <span>View Saved Equations</span>
            </a>
        </div>
    </header>

    {{-- Input Data section --}}
    <section class="dashboard-section model-builder-section fade-in-element mb-section" id="inputDataSection">
        <article class="mb-card">
            <h2 class="mb-section-title">Input Data</h2>
            <p class="mb-section-hint">Editable table: fill required parameters, use <strong>Load Example</strong> for sample data, or <strong>Clear</strong> to start over. <strong>Dependent variable:</strong> Soil_Loss_Sqm. <strong>Candidate predictors:</strong> all columns except Year (Year can be included via Regression Settings).</p>
            <div class="mb-toolbar">
                <button type="button" id="btnLoadExample" class="mb-btn mb-btn-secondary" aria-label="Load example dataset">
                    <i data-lucide="download" class="lucide-icon lucide-icon-sm" aria-hidden="true"></i>
                    Load Example
                </button>
                <button type="button" id="btnClearTable" class="mb-btn mb-btn-outline" aria-label="Clear table">
                    <i data-lucide="eraser" class="lucide-icon lucide-icon-sm" aria-hidden="true"></i>
                    Clear
                </button>
            </div>
            <p class="mb-table-hint">Enter your dataset manually or click <strong>Load Example</strong> to populate sample data.</p>
            <div class="mb-table-card" id="inputTableWrap">
                <div class="mb-table-scroll-wrap">
                    <table class="mb-input-table" id="inputTable" aria-label="Input data table">
                        <thead>
                            <tr>
                                <th class="col-num">#</th>
                                <th class="col-year" data-col="Year" title="Year (excluded as predictor by default)">Year</th>
                                <th data-col="Trop_Depressions" title="Tropical depressions">Trop_Depressions</th>
                                <th data-col="Trop_Storms" title="Tropical storms">Trop_Storms</th>
                                <th data-col="Sev_Trop_Storms" title="Severe tropical storms">Sev_Trop_Storms</th>
                                <th data-col="Typhoons">Typhoons</th>
                                <th data-col="Super_Typhoons">Super_Typhoons</th>
                                <th data-col="Floods">Floods</th>
                                <th data-col="Storm_Surges">Storm_Surges</th>
                                <th data-col="Precipitation_mm">Precipitation_mm</th>
                                <th data-col="Seawall_m">Seawall_m</th>
                                <th data-col="Veg_Area_Sqm">Veg_Area_Sqm</th>
                                <th data-col="Coastal_Elevation">Coastal_Elevation</th>
                                <th class="col-target" data-col="Soil_Loss_Sqm" title="Dependent variable">Soil_Loss_Sqm <span class="required-star">*</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @for ($i = 1; $i <= 10; $i++)
                            <tr data-row="{{ $i }}">
                                <td class="row-num">{{ $i }}</td>
                                <td><input type="number" name="Year" min="1900" max="2100" step="1" placeholder="—" class="mb-input mb-input-year" data-col="Year" aria-label="Year row {{ $i }}"></td>
                                <td><input type="number" name="Trop_Depressions" min="0" step="any" placeholder="0" class="mb-input" data-col="Trop_Depressions"></td>
                                <td><input type="number" name="Trop_Storms" min="0" step="any" placeholder="0" class="mb-input" data-col="Trop_Storms"></td>
                                <td><input type="number" name="Sev_Trop_Storms" min="0" step="any" placeholder="0" class="mb-input" data-col="Sev_Trop_Storms"></td>
                                <td><input type="number" name="Typhoons" min="0" step="any" placeholder="0" class="mb-input" data-col="Typhoons"></td>
                                <td><input type="number" name="Super_Typhoons" min="0" step="any" placeholder="0" class="mb-input" data-col="Super_Typhoons"></td>
                                <td><input type="number" name="Floods" min="0" step="any" placeholder="0" class="mb-input" data-col="Floods"></td>
                                <td><input type="number" name="Storm_Surges" min="0" step="any" placeholder="0" class="mb-input" data-col="Storm_Surges"></td>
                                <td><input type="number" name="Precipitation_mm" min="0" step="any" placeholder="0" class="mb-input" data-col="Precipitation_mm"></td>
                                <td><input type="number" name="Seawall_m" min="0" step="any" placeholder="0" class="mb-input" data-col="Seawall_m"></td>
                                <td><input type="number" name="Veg_Area_Sqm" min="0" step="any" placeholder="0" class="mb-input" data-col="Veg_Area_Sqm"></td>
                                <td><input type="number" name="Coastal_Elevation" step="any" placeholder="0" class="mb-input" data-col="Coastal_Elevation"></td>
                                <td><input type="number" name="Soil_Loss_Sqm" min="0" step="any" placeholder="0" class="mb-input mb-target" data-col="Soil_Loss_Sqm" required aria-label="Soil_Loss_Sqm (dependent)"></td>
                            </tr>
                            @endfor
                        </tbody>
                    </table>
                </div>
            </div>
        </article>
    </section>

    {{-- Regression method note (fixed defaults) --}}
    <section class="dashboard-section model-builder-section fade-in-element mb-section" id="regressionNoteSection">
        <p class="mb-regression-note" aria-live="polite">Using SPSS default Stepwise Regression (Enter ≤ 0.05, Remove ≥ 0.10) with Soil_Loss_Sqm as the dependent variable.</p>
    </section>

    {{-- Run Regression action area --}}
    <section class="dashboard-section model-builder-section fade-in-element mb-section" id="runRegressionSection">
        <div class="mb-run-area">
            <button type="button" id="btnRunRegression" class="mb-btn mb-btn-primary mb-btn-run">
                <i data-lucide="play" class="lucide-icon lucide-icon-md" aria-hidden="true"></i>
                <span>Run Regression</span>
            </button>
            <div id="regressionError" class="mb-error" role="alert" hidden></div>
            <div id="regressionWarnings" class="mb-warnings" role="status" aria-live="polite" hidden></div>
        </div>
    </section>

    {{-- Regression Results section --}}
    <section class="dashboard-section model-builder-section fade-in-element mb-section" id="resultsSection" aria-live="polite">
        <h2 class="mb-section-title"><i data-lucide="bar-chart-2" class="lucide-icon lucide-icon-md" aria-hidden="true"></i> Regression Results</h2>
        <div id="resultsEmpty" class="mb-results-empty" aria-hidden="true">Run a regression to see results.</div>
        <div id="resultsLoading" class="mb-results-loading" hidden aria-hidden="true">
            <span class="mb-spinner"></span>
            <span>Computing regression…</span>
        </div>
        <div id="resultsContent" class="mb-results-content" hidden>
            <article class="mb-card mb-result-card" id="generatedModelCard">
                <h3 class="mb-result-heading">Generated Regression Model</h3>
                <p class="mb-stepwise-note" id="stepwiseNote">Final equation includes only predictors retained by the full stepwise procedure.</p>

                <div id="generatedModelContent">
                    <div class="mb-equation-header">
                        <h4 class="mb-result-title">Final Equation</h4>
                        <button type="button" class="mb-btn mb-btn-outline mb-btn-sm" id="btnCopyEquation" title="Copy equation to clipboard" aria-label="Copy equation">
                            <i data-lucide="copy" class="lucide-icon lucide-icon-sm" aria-hidden="true"></i>
                            Copy
                        </button>
                    </div>
                    <div class="mb-equation-block">
                        <div class="mb-equation-formatted" id="regressionEquationFormatted" aria-label="Regression equation" data-raw-equation="" role="text"></div>
                    </div>
                    <div class="mb-significant-predictors" id="significantPredictorsWrap">
                        <h4 class="mb-result-title">Significant Predictors</h4>
                        <ul id="significantPredictorsList" class="mb-list" aria-label="Significant predictors"></ul>
                    </div>
                    <div class="mb-model-summary">
                        <h4 class="mb-result-title">Model Summary</h4>
                        <div class="mb-metrics-grid">
                            <div class="mb-metric"><span class="mb-metric-label">R²</span><span class="mb-metric-value" id="metricR2">—</span></div>
                            <div class="mb-metric"><span class="mb-metric-label">Adjusted R²</span><span class="mb-metric-value" id="metricAdjR2">—</span></div>
                            <div class="mb-metric"><span class="mb-metric-label">Std. Error of Estimate</span><span class="mb-metric-value" id="metricStdErrEstimate">—</span></div>
                            <div class="mb-metric"><span class="mb-metric-label">Model sig. (p)</span><span class="mb-metric-value" id="metricSignificancePvalue">—</span></div>
                        </div>
                    </div>
                </div>
                <div id="noSignificantPredictors" class="mb-no-significant" hidden>
                    <p>No statistically significant predictors were identified for the selected dataset and settings.</p>
                </div>
            </article>
            <article class="mb-card mb-result-card">
                <h4 class="mb-result-title">Coefficients</h4>
                <div class="mb-table-scroll">
                    <table class="mb-data-table" id="coefficientsTable">
                        <thead>
                            <tr><th>Variable</th><th>Coefficient</th><th>Std. Error</th><th>t</th><th>p-value</th><th>Significant</th></tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </article>
            {{-- Developer Validation (collapsible) --}}
            <article class="mb-card mb-validation-card" id="developerValidationCard" hidden>
                <h4 class="mb-result-title">
                    <button type="button" class="mb-details-toggle" id="validationToggle" aria-expanded="false" aria-controls="validationPanel" data-target="validationPanel">
                        Developer Validation
                    </button>
                </h4>
                <div id="validationPanel" class="mb-validation-panel" hidden aria-hidden="true">
                    <p class="mb-hint">Compare line-by-line with SPSS. Shown when URL contains <code>?validation=1</code>. Full precision and matrices for debugging.</p>
                    <div id="validationContent" class="mb-validation-content"></div>
                </div>
            </article>
        </div>
    </section>

    {{-- Save Equation area --}}
    <section class="dashboard-section model-builder-section fade-in-element mb-section" id="saveEquationSection" aria-label="Save equation">
        <div class="mb-action-bar">
            <button type="button" class="mb-btn mb-btn-primary" id="btnSaveEquation" aria-label="Save equation">
                <i data-lucide="save" class="lucide-icon lucide-icon-sm" aria-hidden="true"></i>
                <span>Save Equation</span>
            </button>
            <button type="button" class="mb-btn mb-btn-outline" id="btnRunNew" aria-label="Run new calculation">
                <i data-lucide="refresh-cw" class="lucide-icon lucide-icon-sm" aria-hidden="true"></i>
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
                    <textarea id="saveEquationFormula" name="formula" readonly rows="8" class="mb-formula-readonly"></textarea>
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
                    <textarea id="editEquationFormula" name="formula" rows="8" class="mb-formula-editable"></textarea>
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
