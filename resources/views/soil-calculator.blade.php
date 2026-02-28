@extends('layouts.dashboard')

@section('title', 'Soil Calculator')

@section('content')
<div class="dashboard-hub" id="soilCalculatorPage"
    data-saved-equations-url="{{ route('saved-equations.index') }}"
    data-calculation-history-store-url="{{ route('calculation-history.store') }}"
    data-ask-tocsea-url="{{ route('ask-tocsea') }}"
    data-ask-tocsea-with-context-url="{{ route('ask-tocsea.with-context') }}"
    data-tree-recommendations-url="{{ route('tree-recommendations.generate') }}"
    data-rerun-payload="{{ $rerunData ? json_encode($rerunData) : '' }}">
    {{-- Page header --}}
    <header class="dashboard-header fade-in-element">
        <div class="header-card">
            <div class="header-main">
                <div>
                    <h1 class="header-title">
                        <i data-lucide="calculator" class="lucide-icon lucide-icon-md" aria-hidden="true"></i>
                        Soil Loss Calculator
                    </h1>
                    <p class="header-location" style="color: rgba(255,255,255,0.9); font-weight: 400;">
                        Predict annual soil loss from coastal areas based on seawall length, precipitation, tropical storms, floods, and soil type
                    </p>
                </div>
            </div>
        </div>
    </header>

    {{-- Row 1: Calculator Form --}}
    <section class="dashboard-section soil-calculator-section soil-calculator-form-section fade-in-element">
        <h2 class="section-title"><i data-lucide="sliders-horizontal" class="lucide-icon lucide-icon-md" aria-hidden="true"></i> Enter Information</h2>
        <div class="soil-calculator-validation-alert" id="soilCalculatorValidationAlert" role="alert" aria-live="polite" hidden>
            <i data-lucide="alert-triangle" class="lucide-icon lucide-icon-sm" aria-hidden="true"></i>
            <span>Please complete all required fields before calculating.</span>
        </div>
        <article class="weather-card soil-calculator-form-card">
            <form id="soilCalculatorForm" class="soil-calculator-form" novalidate>
                @csrf
                <div class="form-group">
                        <label for="saved_equation">Select Saved Equation</label>
                        <select id="saved_equation" name="saved_equation" aria-describedby="saved_equation-hint">
                            <option value="">— Use default (Buguey) —</option>
                            {{-- Options populated by JS from /saved-equations --}}
                        </select>
                        <span class="form-hint" id="saved_equation-hint">Choose a saved equation from Model Builder, or use the default Buguey model.</span>
                        <span class="form-error" id="saved_equation-error" role="alert" hidden></span>
                    </div>
                    <div id="savedEquationDetails" class="soil-saved-equation-details" hidden>
                        <div class="form-group">
                            <span class="soil-saved-name-label">Equation</span>
                            <p id="savedEquationNameDisplay" class="soil-saved-name"></p>
                        </div>
                        <div class="form-group">
                            <span class="soil-saved-formula-label">Formula</span>
                            <pre id="savedEquationFormulaDisplay" class="soil-saved-formula"></pre>
                        </div>
                        <div id="savedEquationInputsWrap" class="soil-saved-inputs"></div>
                    </div>
                    <div id="defaultModelFields">
                    <div class="form-group">
                        <label for="seawall">Seawall Length <span class="form-label-unit">(meters)</span></label>
                        <input type="number" id="seawall" name="seawall" min="0" step="0.01" placeholder="e.g. 150" required
                               aria-describedby="seawall-hint">
                        <span class="form-hint" id="seawall-hint">How long is the wall along the coast?</span>
                        <span class="form-error" id="seawall-error" role="alert" hidden></span>
                    </div>
                    <div class="form-group">
                        <label for="precipitation">Precipitation <span class="form-label-unit">(mm)</span></label>
                        <input type="number" id="precipitation" name="precipitation" min="0" step="0.01" placeholder="e.g. 150" required
                               aria-describedby="precipitation-hint">
                        <span class="form-hint" id="precipitation-hint">Precipitation in millimeters</span>
                        <span class="form-error" id="precipitation-error" role="alert" hidden></span>
                    </div>
                    <div class="form-group">
                        <label for="tropical_storm">Tropical Storm</label>
                        <input type="number" id="tropical_storm" name="tropical_storm" min="0" step="1" placeholder="e.g. 2" required
                               aria-describedby="tropical_storm-hint">
                        <span class="form-hint" id="tropical_storm-hint">Number of tropical storm events</span>
                        <span class="form-error" id="tropical_storm-error" role="alert" hidden></span>
                    </div>
                    <div class="form-group">
                        <label for="floods">Floods per Year</label>
                        <input type="number" id="floods" name="floods" min="0" step="1" placeholder="e.g. 2" required
                               aria-describedby="floods-hint">
                        <span class="form-hint" id="floods-hint">How many floods happen each year?</span>
                        <span class="form-error" id="floods-error" role="alert" hidden></span>
                    </div>
                    </div>
                    <div class="form-group">
                        <label for="soil_type">Soil Type</label>
                        <select id="soil_type" name="soil_type" required aria-describedby="soil_type-hint">
                            <option value="">Select soil type</option>
                            <option value="sandy">Sandy</option>
                            <option value="clay">Clay</option>
                            <option value="loamy">Loamy</option>
                            <option value="silty">Silty</option>
                            <option value="peaty">Peaty</option>
                            <option value="chalky">Chalky</option>
                        </select>
                        <span class="form-hint" id="soil_type-hint">What type of soil is in the coastal area?</span>
                        <span class="form-error" id="soil_type-error" role="alert" hidden></span>
                    </div>
                    <div class="soil-calculator-submit-wrap">
                    <button type="submit" class="btn btn-primary soil-calculator-submit" id="soilCalculatorSubmitBtn" disabled aria-disabled="true">
                        <i data-lucide="calculator" class="lucide-icon lucide-icon-sm" aria-hidden="true"></i>
                        <span>Calculate Soil Loss</span>
                    </button>
                    </div>
                </form>
        </article>
    </section>

    {{-- Row 2: Predicted Soil Loss Result --}}
    <section class="dashboard-section soil-calculator-section soil-calculator-result-section fade-in-element">
        <h2 class="section-title"><i data-lucide="layers" class="lucide-icon lucide-icon-md" aria-hidden="true"></i> Predicted Soil Loss Result</h2>
        <article class="weather-card soil-calculator-result-wrapper" id="resultCard" aria-live="polite" aria-atomic="true">
                {{-- Placeholder: shown when no result yet --}}
                <div class="soil-calculator-result-empty" id="resultEmpty">
                    <i data-lucide="layers" class="lucide-icon lucide-icon-xl" aria-hidden="true"></i>
                    <p>Enter the values above and click <strong>Calculate Soil Loss</strong> to see the predicted annual soil loss.</p>
                </div>

                {{-- Result: analytics dashboard card, shown after calculation --}}
                <div class="soil-calculator-result-card" id="resultContent" hidden>
                    {{-- 1. Top Result Row: label, model badge, value, unit, risk badge --}}
                    <div class="soil-result-top-row">
                        <span class="soil-result-label">Predicted Soil Loss</span>
                        <span class="soil-result-model-badge" id="resultModelBadge">—</span>
                        <div class="soil-result-value-block">
                            <span class="soil-calculator-result-value" id="resultValue">—</span>
                            <span class="soil-calculator-result-unit" id="resultUnit">m²/year</span>
                        </div>
                        <p class="soil-result-negative-note" id="resultNegativeNote" hidden role="note">
                            <i data-lucide="info" class="lucide-icon lucide-icon-xs" aria-hidden="true"></i>
                            Negative value indicates possible soil gain, deposition, or model imbalance. Please review input values.
                        </p>
                        <span class="soil-calculator-risk-badge" id="resultRiskBadge">—</span>
                    </div>

                    {{-- 2. Impact Level & Priority --}}
                    <div class="soil-result-impact-row" id="resultImpactWrap" aria-live="polite">
                        <div class="soil-impact-item">
                            <span class="soil-impact-label">Impact Level</span>
                            <span class="soil-impact-badge" id="resultImpactLevel" title="Indicates the severity of predicted soil erosion.">—</span>
                        </div>
                        <div class="soil-impact-item">
                            <span class="soil-impact-label">Priority</span>
                            <span class="soil-impact-badge" id="resultPriority" title="Recommended monitoring intensity based on impact.">—</span>
                        </div>
                    </div>

                    {{-- 3. Risk Scale Row: horizontal progress indicator --}}
                    <div class="soil-risk-scale-row">
                        <div class="soil-gauge-track">
                            <div class="soil-gauge-fill" id="resultGaugeFill"></div>
                        </div>
                        <div class="soil-gauge-labels">
                            <span>Low</span>
                            <span>Moderate</span>
                            <span>High</span>
                        </div>
                    </div>

                    {{-- Contributing Factors --}}
                    <div class="soil-result-factors-section">
                        <h4 class="soil-insight-title">
                            <i data-lucide="layers" class="lucide-icon lucide-icon-sm" aria-hidden="true"></i>
                            Contributing Factors
                        </h4>
                        <ul class="soil-factors-list" id="resultFactorsList">
                            <li><span class="factor-name">Storm & Flood Impact</span><span class="factor-value factor-badge" id="factorStorm">—</span></li>
                            <li><span class="factor-name">Coastal Protection</span><span class="factor-value factor-badge" id="factorProtection">—</span></li>
                            <li><span class="factor-name">Soil Type Influence</span><span class="factor-value factor-badge" id="factorSoil">—</span></li>
                        </ul>
                    </div>

                    <div class="soil-result-actions">
                        <button type="button" class="soil-action-btn soil-action-secondary" id="btnRunNew">
                            <i data-lucide="rotate-ccw" class="lucide-icon lucide-icon-sm" aria-hidden="true"></i>
                            Run New Calculation
                        </button>
                        <a href="#how-it-works" class="soil-action-btn soil-action-outline" id="btnModelDetails">
                            <i data-lucide="info" class="lucide-icon lucide-icon-sm" aria-hidden="true"></i>
                            View Model Details
                        </a>
                        <button type="button" class="soil-action-btn soil-action-ai" id="btnAskTocsea" aria-label="Ask TOCSEA about this soil loss result">
                            <i data-lucide="message-circle" class="lucide-icon lucide-icon-sm" aria-hidden="true"></i>
                            Ask TOCSEA About This Result
                        </button>
                    </div>
                </div>

                {{-- Loading overlay --}}
                <div class="soil-calculator-result-loading" id="resultLoading" hidden aria-hidden="true">
                    <span class="soil-calculator-loading-spinner"></span>
                    <span>Calculating…</span>
                </div>
        </article>
    </section>

    {{-- Tree & Vegetation Recommendation (shown when results exist, AI-powered) --}}
    <section class="dashboard-section soil-calculator-section soil-tree-recommendation-section fade-in-element" id="treeRecommendationSection" hidden aria-live="polite">
        <div class="soil-tree-recommendation-wrapper">
            <div class="soil-tree-recommendation-loading" id="treeRecLoading" hidden aria-hidden="true">
                <span class="soil-calculator-loading-spinner"></span>
                <span>Generating recommendations…</span>
            </div>
            <div class="soil-tree-recommendation-content" id="treeRecContent">
            <header class="soil-tree-recommendation-header">
                <h2 class="section-title soil-tree-section-title">
                    <i data-lucide="tree-pine" class="lucide-icon lucide-icon-md soil-tree-title-icon" aria-hidden="true"></i>
                    Tree & Vegetation Recommendation
                </h2>
                <p class="soil-tree-recommendation-subtitle">Suggestions based on soil type and erosion risk.</p>
            </header>
            <article class="soil-tree-recommendation-card">
                <div class="soil-tree-recommendation-badges">
                    <span class="soil-tree-badge soil-tree-badge-soil" id="treeRecSoilBadge">—</span>
                    <span class="soil-tree-badge soil-tree-badge-risk" id="treeRecRiskBadge">—</span>
                    <span class="soil-tree-badge soil-tree-badge-goal" id="treeRecGoalBadge">—</span>
                </div>
                <div class="soil-tree-species-section">
                    <h4 class="soil-tree-subtitle soil-tree-subtitle-with-icon">
                        <i data-lucide="leaf" class="lucide-icon lucide-icon-sm" aria-hidden="true"></i>
                        Recommended Species
                    </h4>
                    <ul class="soil-tree-species-list" id="treeRecSpeciesList" role="list">
                        {{-- Populated by JS --}}
                    </ul>
                </div>
                <div class="soil-tree-strategy-section">
                    <h4 class="soil-tree-subtitle soil-tree-subtitle-with-icon">
                        <i data-lucide="layers" class="lucide-icon lucide-icon-sm" aria-hidden="true"></i>
                        Planting Strategy
                    </h4>
                    <div class="soil-tree-strategy-box">
                        <ul class="soil-tree-strategy-list" id="treeRecStrategyList" role="list">
                            {{-- Populated by JS --}}
                        </ul>
                    </div>
                </div>
                {{-- Advisory footer: always-visible reminder (never conditional on AI response) --}}
                <footer class="soil-tree-recommendation-footer soil-tree-advisory-callout soil-tree-advisory-always-visible" role="contentinfo">
                    <i data-lucide="info" class="lucide-icon lucide-icon-sm soil-tree-advisory-icon" aria-hidden="true"></i>
                    <p class="soil-tree-advisory">This output is generated through an AI-based advisory system and shall not replace technical site assessment. Validation with appropriate environmental authorities is strongly advised.</p>
                </footer>
            </article>
            </div>
        </div>
    </section>

    {{-- How it works --}}
    <section class="dashboard-section fade-in-element" id="how-it-works">
        <h2 class="section-title"><i data-lucide="layers" class="lucide-icon lucide-icon-md" aria-hidden="true"></i> How the Calculation Works</h2>
        <article class="weather-card soil-calculator-info-card">
            <p class="soil-calculator-info-intro">The system uses the selected model's formula to predict annual soil loss:</p>
            <p class="soil-calculator-info-model" id="infoModelName">Model: Buguey Regression Model</p>
            <code class="soil-calculator-formula-display" id="infoFormulaDisplay">
                Soil Loss = 81,610.062 − (54.458 × Seawall) + (2,665.351 × Typhoons) + (2,048.205 × Floods)
            </code>
            <ul class="soil-calculator-info-list" id="infoFormulaBreakdown">
                <li><strong>81,610.062</strong> — Base coefficient</li>
                <li><strong>− (54.458 × Seawall)</strong> — Seawalls help reduce soil loss (negative term)</li>
                <li><strong>+ (2,665.351 × Typhoons)</strong> — Typhoons increase soil loss (positive term)</li>
                <li><strong>+ (2,048.205 × Floods)</strong> — Floods increase soil loss (positive term)</li>
            </ul>
        </article>
    </section>
</div>

@push('styles')
    @vite(['resources/css/soil-calculator.css'])
@endpush

@push('scripts')
    @vite(['resources/js/soil-calculator.js'])
@endpush
@endsection
