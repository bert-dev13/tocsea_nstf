/**
 * TOCSEA Soil Calculator - Coastal soil loss prediction
 * Scalable model registry for prediction models + saved equations from Model Builder
 */

/**
 * Parse formula string from Model Builder (e.g. "Soil Loss = 81,610.062\n− (54.458 × Seawall_m)\n+ ...")
 * Returns { intercept, terms: [ { name, coefficient } ], variables: string[] } or null if parse fails.
 */
function parseSavedFormula(formulaStr) {
    if (!formulaStr || typeof formulaStr !== 'string') return null;
    const lines = formulaStr.trim().split('\n').map((s) => s.trim()).filter(Boolean);
    if (lines.length < 1) return null;
    const firstLine = lines[0];
    const eqIndex = firstLine.indexOf('=');
    if (eqIndex === -1) return null;
    const interceptStr = firstLine.slice(eqIndex + 1).trim().replace(/,/g, '');
    const intercept = parseFloat(interceptStr);
    if (Number.isNaN(intercept)) return null;
    const terms = [];
    const termRegex = /^([+\−-])\s*\(([\d,.\s]+)\s*×\s*(\w+)\)/;
    for (let i = 1; i < lines.length; i++) {
        const m = lines[i].match(termRegex);
        if (!m) continue;
        const sign = m[1];
        const coefStr = m[2].replace(/,/g, '').trim();
        const varName = m[3];
        let coef = parseFloat(coefStr);
        if (Number.isNaN(coef)) continue;
        if (sign === '−' || sign === '-') coef = -coef;
        terms.push({ name: varName, coefficient: coef });
    }
    const variables = terms.map((t) => t.name);
    return { intercept, terms, variables };
}

function evaluateSavedFormula(parsed, values) {
    if (!parsed || !values) return NaN;
    let sum = parsed.intercept;
    for (const t of parsed.terms) {
        const v = values[t.name];
        const num = typeof v === 'number' && !Number.isNaN(v) ? v : parseFloat(v);
        if (Number.isNaN(num)) return NaN;
        sum += t.coefficient * num;
    }
    return sum;
}

const PREDICTION_MODELS = {
    buguey: {
        id: 'buguey',
        name: 'Buguey Regression Model',
        formula: (seawall, precipitation, tropicalStorm, flood) => {
            const base = 49218.016;
            const seawallCoef = 61.646;
            const precipitationCoef = 19.931;
            const tropicalStormCoef = 1779.250;
            const floodCoef = 2389.243;
            return base - (seawallCoef * seawall) + (precipitationCoef * precipitation) + (tropicalStormCoef * tropicalStorm) + (floodCoef * flood);
        },
        formulaDisplay: 'Average Predicted Soil Loss = 49,218.016 − 61.646(Seawall) + 19.931(Precipitation) + 1,779.250(Tropical Storm) + 2,389.243(Flood)',
        formatCalculation: (seawall, precipitation, tropicalStorm, flood, result) =>
            `49,218.016 − (61.646 × ${seawall}) + (19.931 × ${precipitation}) + (1,779.250 × ${tropicalStorm}) + (2,389.243 × ${flood}) = ${result.toFixed(2)} m²/year`,
        formulaBreakdown: `
            <li><strong>49,218.016</strong> — Intercept (base)</li>
            <li><strong>− 61.646(Seawall)</strong> — Seawalls help reduce soil loss (negative term)</li>
            <li><strong>+ 19.931(Precipitation)</strong> — Precipitation effect (mm)</li>
            <li><strong>+ 1,779.250(Tropical Storm)</strong> — Tropical storms increase soil loss</li>
            <li><strong>+ 2,389.243(Flood)</strong> — Floods increase soil loss</li>
        `.trim()
    }
};

const DEFAULT_MODEL_ID = 'buguey';

const RISK_THRESHOLDS = { moderateMax: 70000, highMin: 120000 };

const SOIL_EROSION_SUSCEPTIBILITY = {
    sandy: { level: 'high', label: 'High' },
    silty: { level: 'high', label: 'High' },
    peaty: { level: 'moderate', label: 'Moderate' },
    loamy: { level: 'moderate', label: 'Moderate' },
    chalky: { level: 'moderate', label: 'Moderate' },
    clay: { level: 'low', label: 'Low' }
};

function getRiskLevel(soilLoss) {
    if (soilLoss < RISK_THRESHOLDS.moderateMax) return { level: 'low', label: 'Low Risk' };
    if (soilLoss < RISK_THRESHOLDS.highMin) return { level: 'moderate', label: 'Moderate Risk' };
    return { level: 'high', label: 'High Risk' };
}

function getContributingFactors(typhoons, floods, seawall, soilType) {
    const stormScore = Math.min(typhoons + floods, 5);
    const stormLevel = stormScore >= 4 ? 'high' : stormScore >= 2 ? 'moderate' : 'low';
    const protectionValue = seawall >= 200 ? 'high' : seawall >= 100 ? 'moderate' : 'low';
    const protectionColorLevel = seawall >= 200 ? 'low' : seawall >= 100 ? 'moderate' : 'high';
    const soil = SOIL_EROSION_SUSCEPTIBILITY[soilType] || { level: 'moderate', label: 'Moderate' };
    return {
        storm: { level: stormLevel, label: stormLevel.charAt(0).toUpperCase() + stormLevel.slice(1), barLevel: stormLevel },
        protection: { level: protectionColorLevel, label: protectionValue.charAt(0).toUpperCase() + protectionValue.slice(1), barLevel: protectionValue },
        soil: { level: soil.level, label: soil.label, barLevel: soil.level }
    };
}

const IMPACT_PRIORITY = {
    low: {
        impact: 'Low',
        priority: 'Routine Monitoring',
        impactTooltip: 'Low impact — Soil erosion risk is minimal. Standard monitoring is sufficient.',
        priorityTooltip: 'Routine monitoring — Periodic checks recommended.'
    },
    moderate: {
        impact: 'Moderate',
        priority: 'Medium Monitoring Required',
        impactTooltip: 'Moderate impact — Soil erosion is not critical but should be monitored.',
        priorityTooltip: 'Medium monitoring required — Regular assessments recommended.'
    },
    high: {
        impact: 'High',
        priority: 'High Monitoring Required',
        impactTooltip: 'High impact — Soil erosion risk is significant. Immediate attention advised.',
        priorityTooltip: 'High monitoring required — Frequent assessments and mitigation needed.'
    }
};

function getGaugePosition(riskLevel) {
    return { low: 17, moderate: 50, high: 83 }[riskLevel] || 50;
}

/**
 * Tree & Vegetation Recommendations based on soil type, risk level, and hazard intensity.
 * Returns species list, planting strategy, and advisory notes.
 */
const TREE_RECOMMENDATIONS = {
    high: {
        goalBadge: 'Soil Stabilization',
        strategy: [
            'Shoreline buffer — Mangroves and coastal species in intertidal zone.',
            'Inland slopes — Deep-rooted species (vetiver, bamboo).',
            'Ground cover — Dense vegetation to reduce runoff.',
        ],
        speciesBySoil: {
            clay: [
                { name: 'Vetiver Grass', reason: 'Deep root system for slope stabilization' },
                { name: 'Clumping Bamboo', reason: 'Strong root network for soil binding' },
                { name: 'Ipil-Ipil (Leucaena)', reason: 'Nitrogen-fixing, rapid growth for erosion control' },
                { name: 'Talisay', reason: 'Wind-tolerant coastal buffer' },
                { name: 'Mangrove Species', reason: 'Coastal wave energy reduction' },
            ],
            sandy: [
                { name: 'Vetiver Grass', reason: 'Deep root system for slope stabilization' },
                { name: 'Mangrove Species', reason: 'Coastal wave energy reduction' },
                { name: 'Beach Naupaka (Scaevola)', reason: 'Salt-tolerant coastal ground cover' },
                { name: 'Agoho (Casuarina)', reason: 'Windbreak and sand stabilization' },
                { name: 'Nipa Palm', reason: 'Coastal wetland stabilization' },
            ],
            silty: [
                { name: 'Vetiver Grass', reason: 'Deep root system for slope stabilization' },
                { name: 'Clumping Bamboo', reason: 'Strong root network for soil binding' },
                { name: 'Ipil-Ipil (Leucaena)', reason: 'Nitrogen-fixing, rapid growth for erosion control' },
                { name: 'Nipa Palm', reason: 'Coastal wetland stabilization' },
                { name: 'Mangrove Species', reason: 'Coastal wave energy reduction' },
            ],
            loamy: [
                { name: 'Vetiver Grass', reason: 'Deep root system for slope stabilization' },
                { name: 'Clumping Bamboo', reason: 'Strong root network for soil binding' },
                { name: 'Ipil-Ipil (Leucaena)', reason: 'Nitrogen-fixing, rapid growth for erosion control' },
                { name: 'Talisay', reason: 'Wind-tolerant coastal buffer' },
                { name: 'Mangrove Species', reason: 'Coastal wave energy reduction' },
            ],
            peaty: [
                { name: 'Vetiver Grass', reason: 'Deep root system for slope stabilization' },
                { name: 'Nipa Palm', reason: 'Wetland and peat stabilization' },
                { name: 'Mangrove Species', reason: 'Coastal wave energy reduction' },
                { name: 'Clumping Bamboo', reason: 'Strong root network for soil binding' },
            ],
            chalky: [
                { name: 'Vetiver Grass', reason: 'Deep root system for slope stabilization' },
                { name: 'Clumping Bamboo', reason: 'Strong root network for soil binding' },
                { name: 'Talisay', reason: 'Wind-tolerant coastal buffer' },
                { name: 'Ipil-Ipil (Leucaena)', reason: 'Nitrogen-fixing, rapid growth for erosion control' },
            ],
        },
    },
    moderate: {
        goalBadge: 'Vegetation Reinforcement',
        strategy: [
            'Shoreline buffer — Coastal vegetation belt.',
            'Inland slopes — Grasses and shrubs for reinforcement.',
            'Ground cover — Maintain vegetative cover.',
        ],
        speciesBySoil: {
            clay: [
                { name: 'Vetiver Grass', reason: 'Deep roots for slope stabilization' },
                { name: 'Clumping Bamboo', reason: 'Soil binding and windbreak' },
                { name: 'Ipil-Ipil (Leucaena)', reason: 'Nitrogen-fixing, erosion control' },
                { name: 'Talisay', reason: 'Coastal buffer species' },
            ],
            sandy: [
                { name: 'Vetiver Grass', reason: 'Deep roots for slope stabilization' },
                { name: 'Beach Naupaka (Scaevola)', reason: 'Salt-tolerant coastal cover' },
                { name: 'Agoho (Casuarina)', reason: 'Windbreak and sand stabilization' },
                { name: 'Mangrove Species', reason: 'Coastal protection' },
            ],
            silty: [
                { name: 'Vetiver Grass', reason: 'Deep roots for slope stabilization' },
                { name: 'Clumping Bamboo', reason: 'Soil binding' },
                { name: 'Ipil-Ipil (Leucaena)', reason: 'Nitrogen-fixing, erosion control' },
                { name: 'Nipa Palm', reason: 'Wetland stabilization' },
            ],
            loamy: [
                { name: 'Vetiver Grass', reason: 'Deep roots for slope stabilization' },
                { name: 'Clumping Bamboo', reason: 'Soil binding and windbreak' },
                { name: 'Ipil-Ipil (Leucaena)', reason: 'Nitrogen-fixing, erosion control' },
                { name: 'Talisay', reason: 'Coastal buffer species' },
            ],
            peaty: [
                { name: 'Vetiver Grass', reason: 'Deep roots for slope stabilization' },
                { name: 'Nipa Palm', reason: 'Wetland stabilization' },
                { name: 'Mangrove Species', reason: 'Coastal protection' },
            ],
            chalky: [
                { name: 'Vetiver Grass', reason: 'Deep roots for slope stabilization' },
                { name: 'Clumping Bamboo', reason: 'Soil binding' },
                { name: 'Talisay', reason: 'Coastal buffer species' },
            ],
        },
        engineeringNote: null,
    },
    low: {
        goalBadge: 'Ground Cover',
        strategy: [
            'Shoreline — Maintain existing coastal vegetation.',
            'Inland — Light ground cover and grasses.',
        ],
        speciesBySoil: {
            clay: [
                { name: 'Vetiver Grass', reason: 'Low-maintenance slope stabilization' },
                { name: 'Carabao Grass', reason: 'Ground cover for soil retention' },
                { name: 'Ipil-Ipil (Leucaena)', reason: 'Nitrogen-fixing, light cover' },
            ],
            sandy: [
                { name: 'Beach Naupaka (Scaevola)', reason: 'Salt-tolerant coastal ground cover' },
                { name: 'Carabao Grass', reason: 'Ground cover for soil retention' },
                { name: 'Vetiver Grass', reason: 'Deep roots for slope stabilization' },
            ],
            silty: [
                { name: 'Vetiver Grass', reason: 'Low-maintenance slope stabilization' },
                { name: 'Carabao Grass', reason: 'Ground cover for soil retention' },
                { name: 'Ipil-Ipil (Leucaena)', reason: 'Nitrogen-fixing, light cover' },
            ],
            loamy: [
                { name: 'Vetiver Grass', reason: 'Low-maintenance slope stabilization' },
                { name: 'Carabao Grass', reason: 'Ground cover for soil retention' },
                { name: 'Ipil-Ipil (Leucaena)', reason: 'Nitrogen-fixing, light cover' },
            ],
            peaty: [
                { name: 'Nipa Palm', reason: 'Wetland maintenance' },
                { name: 'Carabao Grass', reason: 'Ground cover for soil retention' },
            ],
            chalky: [
                { name: 'Vetiver Grass', reason: 'Low-maintenance slope stabilization' },
                { name: 'Carabao Grass', reason: 'Ground cover for soil retention' },
            ],
        },
    },
};

function getTreeRecommendations(soilType, riskLevel) {
    const soil = (soilType || 'loamy').toLowerCase();
    const risk = (riskLevel || 'moderate').toLowerCase();
    const config = TREE_RECOMMENDATIONS[risk] || TREE_RECOMMENDATIONS.moderate;
    const species = config.speciesBySoil[soil] || config.speciesBySoil.loamy;
    return {
        goalBadge: config.goalBadge || 'Soil Stabilization',
        species,
        strategy: config.strategy,
    };
}

function getBarWidth(level) {
    return { high: 100, moderate: 60, low: 25 }[level] || 50;
}

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('soilCalculatorForm');
    if (!form) return;

    const savedEquationSelect = document.getElementById('saved_equation');
    const savedEquationDetails = document.getElementById('savedEquationDetails');
    const defaultModelFields = document.getElementById('defaultModelFields');
    const savedEquationNameDisplay = document.getElementById('savedEquationNameDisplay');
    const savedEquationFormulaDisplay = document.getElementById('savedEquationFormulaDisplay');
    const savedEquationInputsWrap = document.getElementById('savedEquationInputsWrap');
    const submitBtn = form.querySelector('.soil-calculator-submit');
    const resultEmpty = document.getElementById('resultEmpty');
    const resultContent = document.getElementById('resultContent');
    const resultLoading = document.getElementById('resultLoading');
    const resultValue = document.getElementById('resultValue');
    const resultUnit = document.getElementById('resultUnit');
    const resultModelBadge = document.getElementById('resultModelBadge');
    const resultRiskBadge = document.getElementById('resultRiskBadge');
    const resultImpactLevel = document.getElementById('resultImpactLevel');
    const resultPriority = document.getElementById('resultPriority');
    const resultGaugeFill = document.getElementById('resultGaugeFill');
    const factorStorm = document.getElementById('factorStorm');
    const factorProtection = document.getElementById('factorProtection');
    const factorSoil = document.getElementById('factorSoil');
    const btnRunNew = document.getElementById('btnRunNew');
    const btnAskTocsea = document.getElementById('btnAskTocsea');
    const treeRecSection = document.getElementById('treeRecommendationSection');
    const treeRecLoading = document.getElementById('treeRecLoading');
    const treeRecContent = document.getElementById('treeRecContent');
    const treeRecSoilBadge = document.getElementById('treeRecSoilBadge');
    const treeRecRiskBadge = document.getElementById('treeRecRiskBadge');
    const treeRecGoalBadge = document.getElementById('treeRecGoalBadge');
    const treeRecGroundCoverSection = document.getElementById('treeRecGroundCoverSection');
    const treeRecGroundCoverList = document.getElementById('treeRecGroundCoverList');
    const treeRecCoastalTreesSection = document.getElementById('treeRecCoastalTreesSection');
    const treeRecCoastalTreesList = document.getElementById('treeRecCoastalTreesList');
    const treeRecSpeciesSection = document.getElementById('treeRecSpeciesSection');
    const treeRecSpeciesList = document.getElementById('treeRecSpeciesList');
    const treeRecStrategyList = document.getElementById('treeRecStrategyList');
    const infoModelName = document.getElementById('infoModelName');
    const validationAlert = document.getElementById('soilCalculatorValidationAlert');
    const infoFormulaDisplay = document.getElementById('infoFormulaDisplay');
    const infoFormulaBreakdown = document.getElementById('infoFormulaBreakdown');

    let savedEquationsList = [];
    /** Last calculation context for "Ask TOCSEA About This Result" */
    let lastCalculationContext = null;

    function getSavedEquationsUrl() {
        const page = document.getElementById('soilCalculatorPage');
        return page?.dataset?.savedEquationsUrl || '/saved-equations';
    }

    function getCalculationHistoryStoreUrl() {
        const page = document.getElementById('soilCalculatorPage');
        return page?.dataset?.calculationHistoryStoreUrl || '';
    }

    function saveCalculationToHistory(payload) {
        const url = getCalculationHistoryStoreUrl();
        if (!url) return Promise.resolve();
        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        return fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrf || '',
            },
            body: JSON.stringify(payload),
        }).catch(() => {});
    }

    async function loadSavedEquations() {
        try {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const res = await fetch(getSavedEquationsUrl(), {
                method: 'GET',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf || '' },
            });
            const data = await res.json().catch(() => ({}));
            savedEquationsList = data?.equations || [];
            if (savedEquationSelect) {
                const firstOpt = savedEquationSelect.querySelector('option[value=""]');
                savedEquationSelect.innerHTML = '';
                if (firstOpt) savedEquationSelect.appendChild(firstOpt);
                savedEquationsList.forEach((eq) => {
                    const opt = document.createElement('option');
                    opt.value = String(eq.id);
                    opt.textContent = eq.equation_name;
                    savedEquationSelect.appendChild(opt);
                });
            }
        } catch (_) {}
    }

    function onSavedEquationChange() {
        const value = savedEquationSelect?.value?.trim();
        if (!value) {
            if (savedEquationDetails) savedEquationDetails.hidden = true;
            if (defaultModelFields) defaultModelFields.hidden = false;
            updateInfoSection(PREDICTION_MODELS[DEFAULT_MODEL_ID]);
            return;
        }
        const eq = savedEquationsList.find((e) => String(e.id) === value);
        if (!eq) {
            if (savedEquationDetails) savedEquationDetails.hidden = true;
            if (defaultModelFields) defaultModelFields.hidden = false;
            return;
        }
        if (defaultModelFields) defaultModelFields.hidden = true;
        if (savedEquationDetails) savedEquationDetails.hidden = false;
        if (savedEquationNameDisplay) savedEquationNameDisplay.textContent = eq.equation_name;
        if (savedEquationFormulaDisplay) savedEquationFormulaDisplay.textContent = eq.formula || '';
        const parsed = parseSavedFormula(eq.formula);
        savedEquationInputsWrap.innerHTML = '';
        if (parsed && parsed.variables.length > 0) {
            parsed.variables.forEach((varName) => {
                const group = document.createElement('div');
                group.className = 'form-group';
                const id = 'saved_var_' + varName.replace(/\W/g, '_');
                group.innerHTML = `<label for="${id}">${varName}</label><input type="number" id="${id}" name="saved_${varName}" data-variable="${varName}" min="-999999" step="0.01" placeholder="0"><span class="form-error" id="${id}-error" role="alert" hidden></span>`;
                savedEquationInputsWrap.appendChild(group);
            });
        }
        if (infoModelName) infoModelName.textContent = `Model: ${eq.equation_name}`;
        if (infoFormulaDisplay) infoFormulaDisplay.textContent = eq.formula || '';
        if (infoFormulaBreakdown) infoFormulaBreakdown.innerHTML = '';
    }

    const inputsToWatch = ['seawall', 'precipitation', 'tropical_storm', 'floods', 'soil_type', 'saved_equation'];

    function getSelectedModel() {
        const useSaved = savedEquationSelect?.value?.trim();
        if (useSaved) {
            const eq = savedEquationsList.find((e) => String(e.id) === useSaved);
            if (eq) {
                const parsed = parseSavedFormula(eq.formula);
                return parsed ? { id: 'saved', name: eq.equation_name, formula: eq.formula, parsed } : PREDICTION_MODELS[DEFAULT_MODEL_ID];
            }
        }
        return PREDICTION_MODELS[DEFAULT_MODEL_ID];
    }

    function updateInfoSection(model) {
        if (!model || !infoModelName || !infoFormulaDisplay) return;
        infoModelName.textContent = `Model: ${model.name}`;
        infoFormulaDisplay.textContent = model.formulaDisplay || model.formula || '—';
        if (infoFormulaBreakdown) infoFormulaBreakdown.innerHTML = model.formulaBreakdown || '';
    }

    function showPlaceholder() {
        if (resultEmpty) resultEmpty.removeAttribute('hidden');
        if (resultContent) resultContent.setAttribute('hidden', '');
        if (treeRecSection) treeRecSection.setAttribute('hidden', '');
    }

    function showResult() {
        if (resultEmpty) resultEmpty.setAttribute('hidden', '');
        if (resultContent) resultContent.removeAttribute('hidden');
    }

    /** Cache for tree recommendations: { key, data } to avoid repeated API calls for same calculation */
    let treeRecCache = null;

    async function populateTreeRecommendations(context) {
        if (!treeRecSection || !treeRecSoilBadge || !treeRecRiskBadge || !treeRecGoalBadge || !treeRecSpeciesList || !treeRecStrategyList) return;
        const { soilType, riskLevel, soilLoss, hazardValues, modelName, impactSummary } = context;
        const soilLabel = String(soilType || 'Loamy').charAt(0).toUpperCase() + String(soilType || 'loamy').slice(1);
        const riskLabel = String(riskLevel || 'Moderate').charAt(0).toUpperCase() + String(riskLevel || 'moderate').slice(1);

        treeRecSection.removeAttribute('hidden');
        treeRecSoilBadge.textContent = soilLabel;
        treeRecRiskBadge.textContent = riskLabel;
        treeRecRiskBadge.className = 'soil-tree-badge soil-tree-badge-risk risk-' + (riskLevel || 'moderate');

        const hv = hazardValues || {};
        const cacheKey = JSON.stringify({ soilType, riskLevel, soilLoss, hazardValues, modelName, impactSummary });
        if (treeRecCache && treeRecCache.key === cacheKey) {
            renderTreeRecFromData(treeRecCache.data, soilType, riskLevel);
            if (treeRecSection) treeRecSection.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            return;
        }
        if (treeRecLoading) treeRecLoading.removeAttribute('hidden');
        if (treeRecContent) treeRecContent.setAttribute('hidden', '');

        const apiUrl = document.getElementById('soilCalculatorPage')?.dataset?.treeRecommendationsUrl || '/api/tree-recommendations';
        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

        try {
            const res = await fetch(apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrf || '',
                },
                body: JSON.stringify({
                    soil_type: soilType || 'loamy',
                    predicted_soil_loss: soilLoss,
                    risk_level: riskLevel || 'moderate',
                    hazard_values: hv,
                    model_name: modelName || null,
                    impact_summary: impactSummary || null,
                    seawall_length: hv.Seawall_m ?? hv.seawall ?? null,
                    precipitation: hv.Precipitation_mm ?? hv.precipitation ?? null,
                    tropical_storms: hv.Tropical_Storms ?? hv.tropical_storm ?? null,
                    floods: hv.Floods ?? hv.floods ?? null,
                }),
            });

            const data = await res.json().catch(() => ({}));

            if (!res.ok || data.error) {
                throw new Error(data.error || 'Failed to load recommendations');
            }

            treeRecCache = { key: cacheKey, data };
            renderTreeRecFromData(data, soilType, riskLevel);
        } catch (err) {
            const rec = getTreeRecommendations(soilType, riskLevel);
            treeRecGoalBadge.textContent = rec.goalBadge;
            if (treeRecGroundCoverSection) treeRecGroundCoverSection.setAttribute('hidden', '');
            if (treeRecCoastalTreesSection) treeRecCoastalTreesSection.setAttribute('hidden', '');
            if (treeRecSpeciesSection) treeRecSpeciesSection.removeAttribute('hidden');
            treeRecSpeciesList.innerHTML = rec.species.map((s) => `<li><strong>${escapeHtml(s.name)}</strong><span class="soil-tree-reason">${escapeHtml(s.reason)}</span></li>`).join('');
            treeRecStrategyList.innerHTML = rec.strategy.map((s) => `<li>${escapeHtml(s)}</li>`).join('');
        } finally {
            if (treeRecLoading) treeRecLoading.setAttribute('hidden', '');
            if (treeRecContent) treeRecContent.removeAttribute('hidden');
            if (treeRecSection) treeRecSection.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function renderTreeRecFromData(data, soilType, riskLevel) {
        const groundCover = data.ground_cover || [];
        const coastalTrees = data.coastal_trees || [];
        const strategyObj = data.planting_strategy || {};
        const species = data.recommended_species || [];
        const strategyArray = data.planting_strategy_array || [];
        const rec = getTreeRecommendations(soilType, riskLevel);

        const hasStructured = groundCover.length > 0 || coastalTrees.length > 0;
        treeRecGoalBadge.textContent = (hasStructured || species.length) ? 'AI Recommendations' : rec.goalBadge;

        if (hasStructured) {
            if (treeRecGroundCoverSection && treeRecGroundCoverList) {
                if (groundCover.length > 0) {
                    treeRecGroundCoverSection.removeAttribute('hidden');
                    treeRecGroundCoverList.innerHTML = groundCover.map((s) => {
                        const reason = s.reason || '';
                        return `<li><strong>${escapeHtml(s.name)}</strong><span class="soil-tree-reason">${escapeHtml(reason)}</span></li>`;
                    }).join('');
                } else {
                    treeRecGroundCoverSection.setAttribute('hidden', '');
                    treeRecGroundCoverList.innerHTML = '';
                }
            }
            if (treeRecCoastalTreesSection && treeRecCoastalTreesList) {
                if (coastalTrees.length > 0) {
                    treeRecCoastalTreesSection.removeAttribute('hidden');
                    treeRecCoastalTreesList.innerHTML = coastalTrees.map((s) => {
                        const reason = s.reason || '';
                        return `<li><strong>${escapeHtml(s.name)}</strong><span class="soil-tree-reason">${escapeHtml(reason)}</span></li>`;
                    }).join('');
                } else {
                    treeRecCoastalTreesSection.setAttribute('hidden', '');
                    treeRecCoastalTreesList.innerHTML = '';
                }
            }
            if (treeRecSpeciesSection) treeRecSpeciesSection.setAttribute('hidden', '');
            const strategyLines = [
                strategyObj.shoreline ? `Shoreline — ${strategyObj.shoreline}` : null,
                strategyObj.mid_slope ? `Mid-Slope — ${strategyObj.mid_slope}` : null,
                strategyObj.inland ? `Inland — ${strategyObj.inland}` : null,
            ].filter(Boolean);
            treeRecStrategyList.innerHTML = strategyLines.length > 0
                ? strategyLines.map((s) => `<li>${escapeHtml(s)}</li>`).join('')
                : rec.strategy.map((s) => `<li>${escapeHtml(s)}</li>`).join('');
        } else {
            if (treeRecGroundCoverSection) treeRecGroundCoverSection.setAttribute('hidden', '');
            if (treeRecCoastalTreesSection) treeRecCoastalTreesSection.setAttribute('hidden', '');
            if (treeRecSpeciesSection) treeRecSpeciesSection.removeAttribute('hidden');
            treeRecSpeciesList.innerHTML = species.length
                ? species.map((s) => `<li><strong>${escapeHtml(s.name)}</strong><span class="soil-tree-reason">${escapeHtml(s.reason)}</span></li>`).join('')
                : rec.species.map((s) => `<li><strong>${escapeHtml(s.name)}</strong><span class="soil-tree-reason">${escapeHtml(s.reason)}</span></li>`).join('');
            treeRecStrategyList.innerHTML = strategyArray.length
                ? strategyArray.map((s) => `<li>${escapeHtml(s)}</li>`).join('')
                : rec.strategy.map((s) => `<li>${escapeHtml(s)}</li>`).join('');
        }
    }

    function areInputsEmpty() {
        const soilType = document.getElementById('soil_type')?.value?.trim();
        const savedId = savedEquationSelect?.value?.trim();
        if (savedId) {
            const eq = savedEquationsList.find((e) => String(e.id) === savedId);
            const parsed = parseSavedFormula(eq?.formula);
            if (parsed && parsed.variables.length) {
                const allFilled = parsed.variables.every((v) => {
                    const input = document.querySelector(`input[data-variable="${v}"]`);
                    return input && input.value?.trim() !== '';
                });
                return !allFilled || !soilType;
            }
            return true;
        }
        const seawall = document.getElementById('seawall')?.value?.trim();
        const precipitation = document.getElementById('precipitation')?.value?.trim();
        const tropicalStorm = document.getElementById('tropical_storm')?.value?.trim();
        const floods = document.getElementById('floods')?.value?.trim();
        return !seawall || !precipitation || !tropicalStorm || !floods || !soilType;
    }

    /** Check if all required fields are valid (for button enable/disable). */
    function isFormValid() {
        const soilType = document.getElementById('soil_type')?.value?.trim() || '';
        const savedId = savedEquationSelect?.value?.trim();

        if (savedId) {
            const eq = savedEquationsList.find((e) => String(e.id) === savedId);
            const parsed = parseSavedFormula(eq?.formula);
            if (!parsed || !parsed.variables.length) return false;
            const allValid = parsed.variables.every((varName) => {
                const input = document.querySelector(`input[data-variable="${varName}"]`);
                const val = input?.value?.trim();
                const num = val === '' ? NaN : parseFloat(val);
                return !Number.isNaN(num) && num >= 0;
            });
            return allValid && soilType !== '';
        }

        const seawall = parseFloat(document.getElementById('seawall')?.value);
        const precipitation = parseFloat(document.getElementById('precipitation')?.value);
        const tropicalStorm = parseFloat(document.getElementById('tropical_storm')?.value);
        const floods = parseFloat(document.getElementById('floods')?.value);
        const numValid = !Number.isNaN(seawall) && seawall >= 0 &&
            !Number.isNaN(precipitation) && precipitation >= 0 &&
            !Number.isNaN(tropicalStorm) && tropicalStorm >= 0 &&
            !Number.isNaN(floods) && floods >= 0;
        return numValid && soilType !== '';
    }

    function updateSubmitButtonState() {
        if (!submitBtn) return;
        const valid = isFormValid();
        submitBtn.disabled = !valid;
    }

    function hideValidationAlert() {
        if (validationAlert) validationAlert.setAttribute('hidden', '');
    }

    function showValidationAlert() {
        if (validationAlert) validationAlert.removeAttribute('hidden');
    }

    function initInputWatchers() {
        function onFieldChange() {
            if (areInputsEmpty() && resultContent && !resultContent.hasAttribute('hidden')) {
                showPlaceholder();
                resultContent.setAttribute('hidden', '');
            }
            updateSubmitButtonState();
            hideValidationAlert();
        }
        inputsToWatch.forEach((id) => {
            const el = document.getElementById(id);
            el?.addEventListener('input', onFieldChange);
            el?.addEventListener('change', onFieldChange);
        });
        form?.addEventListener('input', (e) => {
            if (e.target?.dataset?.variable) onFieldChange();
        });
        form?.addEventListener('change', (e) => {
            if (e.target?.dataset?.variable) onFieldChange();
        });
    }

    savedEquationSelect?.addEventListener('change', () => {
        onSavedEquationChange();
        updateSubmitButtonState();
    });
    loadSavedEquations().then(() => {
        onSavedEquationChange();
        updateSubmitButtonState();
        const rerunPayloadRaw = document.getElementById('soilCalculatorPage')?.dataset?.rerunPayload?.trim();
        if (rerunPayloadRaw) {
            try {
                const rerun = JSON.parse(rerunPayloadRaw);
                if (rerun && typeof rerun.inputs === 'object') {
                    if (rerun.saved_equation_id != null && rerun.saved_equation_id !== '') {
                        if (savedEquationSelect) {
                            savedEquationSelect.value = String(rerun.saved_equation_id);
                            onSavedEquationChange();
                        }
                    }
                    setTimeout(() => {
                        const inputs = rerun.inputs || {};
                        Object.keys(inputs).forEach((key) => {
                            if (key === 'soil_type') {
                                const soilEl = document.getElementById('soil_type');
                                if (soilEl) soilEl.value = inputs[key] || '';
                                return;
                            }
                            const defaultNames = ['seawall', 'precipitation', 'tropical_storm', 'floods'];
                            if (defaultNames.includes(key)) {
                                const el = document.getElementById(key);
                                if (el) el.value = inputs[key] != null ? String(inputs[key]) : '';
                                return;
                            }
                            const savedInput = document.querySelector(`input[data-variable="${key}"]`);
                            if (savedInput) savedInput.value = inputs[key] != null ? String(inputs[key]) : '';
                        });
                        updateSubmitButtonState();
                    }, 100);
                }
            } catch (_) {}
        }
    });
    initInputWatchers();

    function clearErrors() {
        document.querySelectorAll('#soilCalculatorForm .form-error').forEach(el => {
            el.setAttribute('hidden', '');
            el.textContent = '';
        });
        document.querySelectorAll('#soilCalculatorForm input, #soilCalculatorForm select').forEach(el => {
            el.classList.remove('is-invalid');
            el.removeAttribute('aria-invalid');
        });
    }

    function showError(fieldId, message) {
        const input = document.getElementById(fieldId);
        const errorEl = document.getElementById(fieldId + '-error');
        if (input) {
            input.classList.add('is-invalid');
            input.setAttribute('aria-invalid', 'true');
        }
        if (errorEl) {
            errorEl.textContent = message;
            errorEl.removeAttribute('hidden');
        }
    }

    function validateForm() {
        clearErrors();
        let valid = true;
        const useSavedId = savedEquationSelect?.value?.trim();
        const soilType = document.getElementById('soil_type')?.value?.trim() || '';

        if (useSavedId) {
            const eq = savedEquationsList.find((e) => String(e.id) === useSavedId);
            const parsed = parseSavedFormula(eq?.formula);
            if (!parsed || !parsed.variables.length) {
                showError('saved_equation', 'Invalid saved equation or formula.');
                return false;
            }
            const values = {};
            parsed.variables.forEach((varName) => {
                const input = document.querySelector(`input[data-variable="${varName}"]`);
                const val = input?.value?.trim();
                const num = val === '' ? NaN : parseFloat(val);
                if (Number.isNaN(num)) {
                    showError(input?.id || 'saved_equation', `Enter a valid number for ${varName}.`);
                    valid = false;
                } else if (num < 0) {
                    showError(input?.id || 'saved_equation', `Enter a value of 0 or more for ${varName}.`);
                    valid = false;
                } else {
                    values[varName] = num;
                }
            });
            if (!soilType) {
                showError('soil_type', 'Please select a soil type.');
                valid = false;
            }
            if (!valid) return false;
            return { useSaved: true, model: { name: eq.equation_name, parsed }, values, soilType };
        }

        const seawall = parseFloat(document.getElementById('seawall')?.value);
        const precipitation = parseFloat(document.getElementById('precipitation')?.value);
        const tropicalStorm = parseFloat(document.getElementById('tropical_storm')?.value);
        const floods = parseFloat(document.getElementById('floods')?.value);
        if (isNaN(seawall) || seawall < 0) {
            showError('seawall', 'Enter a valid seawall length (0 or more meters).');
            valid = false;
        }
        if (isNaN(precipitation) || precipitation < 0) {
            showError('precipitation', 'Enter a valid precipitation value (0 or more mm).');
            valid = false;
        }
        if (isNaN(tropicalStorm) || tropicalStorm < 0) {
            showError('tropical_storm', 'Enter a valid number of tropical storms (0 or more).');
            valid = false;
        }
        if (isNaN(floods) || floods < 0) {
            showError('floods', 'Enter a valid number of floods per year (0 or more).');
            valid = false;
        }
        if (!soilType) {
            showError('soil_type', 'Please select a soil type.');
            valid = false;
        }
        if (!valid) return false;
        const model = PREDICTION_MODELS[DEFAULT_MODEL_ID];
        return { seawall, precipitation, tropicalStorm, floods, soilType, model };
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const validated = validateForm();
        if (!validated) {
            showValidationAlert();
            const firstInvalid = form.querySelector('input.is-invalid, select.is-invalid');
            if (firstInvalid) firstInvalid.focus();
            return;
        }

        hideValidationAlert();
        resultLoading?.removeAttribute('hidden');
        if (submitBtn) submitBtn.disabled = true;
        resultEmpty?.setAttribute('hidden', '');
        resultContent?.setAttribute('hidden', '');

        await new Promise(r => setTimeout(r, 400));

        let soilLoss;
        if (validated.useSaved && validated.model?.parsed && validated.values) {
            soilLoss = evaluateSavedFormula(validated.model.parsed, validated.values);
        } else {
            const { seawall, precipitation, tropicalStorm, floods, model } = validated;
            soilLoss = model.formula(seawall, precipitation, tropicalStorm, floods);
        }
        soilLoss = Number.isNaN(soilLoss) ? 0 : soilLoss;
        const risk = getRiskLevel(soilLoss);
        const impactInfo = IMPACT_PRIORITY[risk.level] || IMPACT_PRIORITY.moderate;
        const gaugePos = getGaugePosition(risk.level);
        const typhoonsForFactors = validated.useSaved && validated.values
            ? (validated.values.Tropical_Storm ?? validated.values.tropical_storm ?? validated.values.Typhoons ?? validated.values.typhoons ?? 0)
            : (validated.tropicalStorm ?? 0);
        const floodsForFactors = validated.useSaved && validated.values
            ? (validated.values.Floods ?? validated.values.floods ?? 0)
            : (validated.floods ?? 0);
        const seawallForFactors = validated.useSaved && validated.values
            ? (validated.values.Seawall_m ?? validated.values.seawall ?? 0)
            : (validated.seawall ?? 0);
        const factors = getContributingFactors(typhoonsForFactors, floodsForFactors, seawallForFactors, validated.soilType);

        const modelName = validated.model?.name || (PREDICTION_MODELS[DEFAULT_MODEL_ID] && !validated.useSaved ? PREDICTION_MODELS[DEFAULT_MODEL_ID].name : 'Model');
        const isNegative = soilLoss < 0;
        if (resultValue) {
            resultValue.textContent = Number.isNaN(soilLoss) ? '—' : parseFloat(Number(soilLoss).toFixed(2)).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
        const resultValueBlock = document.querySelector('.soil-result-value-block');
        const resultNegativeNote = document.getElementById('resultNegativeNote');
        if (resultValueBlock) resultValueBlock.dataset.negative = isNegative ? 'true' : 'false';
        if (resultNegativeNote) {
            if (isNegative) resultNegativeNote.removeAttribute('hidden');
            else resultNegativeNote.setAttribute('hidden', '');
        }
        if (resultUnit) resultUnit.textContent = 'm²/year';
        if (resultModelBadge) resultModelBadge.textContent = modelName;
        if (resultRiskBadge) {
            resultRiskBadge.textContent = risk.label;
            resultRiskBadge.className = 'soil-calculator-risk-badge risk-' + risk.level;
        }
        if (resultImpactLevel) {
            resultImpactLevel.textContent = impactInfo.impact;
            resultImpactLevel.className = 'soil-impact-badge soil-impact-badge--' + risk.level;
            resultImpactLevel.title = impactInfo.impactTooltip;
        }
        if (resultPriority) {
            resultPriority.textContent = impactInfo.priority;
            resultPriority.className = 'soil-impact-badge soil-impact-badge--' + risk.level;
            resultPriority.title = impactInfo.priorityTooltip;
        }
        if (resultGaugeFill) {
            resultGaugeFill.style.width = gaugePos + '%';
            resultGaugeFill.className = 'soil-gauge-fill gauge-' + risk.level;
        }
        if (factorStorm) {
            factorStorm.textContent = factors.storm.label;
            factorStorm.className = 'factor-value level-' + factors.storm.level;
        }
        if (factorProtection) {
            factorProtection.textContent = factors.protection.label;
            factorProtection.className = 'factor-value level-' + factors.protection.level;
        }
        if (factorSoil) {
            factorSoil.textContent = factors.soil.label;
            factorSoil.className = 'factor-value level-' + factors.soil.level;
        }
        resultLoading?.setAttribute('hidden', '');
        if (submitBtn) submitBtn.disabled = false;
        showResult();

        const hazardValues = validated.useSaved && validated.values
            ? { ...validated.values, soil_type: validated.soilType || '' }
            : {
                seawall: validated.seawall,
                precipitation: validated.precipitation,
                tropical_storm: validated.tropicalStorm,
                floods: validated.floods,
                soil_type: validated.soilType || '',
            };
        const impactSummary = (impactInfo?.impact && impactInfo?.priority)
            ? `${impactInfo.impact}. ${impactInfo.priority}.`
            : (impactInfo?.impact || impactInfo?.priority || null);
        populateTreeRecommendations({
            soilType: validated.soilType || 'loamy',
            riskLevel: risk.level,
            soilLoss,
            hazardValues,
            modelName,
            impactSummary,
        });

        // Store context for "Ask TOCSEA About This Result"
        lastCalculationContext = {
            model_name: modelName,
            equation: validated.useSaved && validated.model?.formula
                ? validated.model.formula
                : (PREDICTION_MODELS[DEFAULT_MODEL_ID]?.formulaDisplay || ''),
            inputs: validated.useSaved && validated.values
                ? { ...validated.values, soil_type: validated.soilType || '' }
                : {
                    seawall: validated.seawall,
                    precipitation: validated.precipitation,
                    tropical_storm: validated.tropicalStorm,
                    floods: validated.floods,
                    soil_type: validated.soilType || '',
                },
            result: {
                predicted_soil_loss: String(soilLoss),
                units: 'm²/year',
            },
            risk_level: risk.label,
            contributing_factors: {
                storm: factors.storm.label,
                protection: factors.protection.label,
                soil: factors.soil.label,
            },
        };

        const storeUrl = getCalculationHistoryStoreUrl();
        if (storeUrl && !Number.isNaN(soilLoss)) {
            let payload = {
                equation_name: modelName,
                formula_snapshot: validated.useSaved && validated.model?.formula
                    ? validated.model.formula
                    : (PREDICTION_MODELS[DEFAULT_MODEL_ID]?.formulaDisplay || ''),
                inputs: {},
                result: soilLoss,
                notes: null,
            };
            if (validated.useSaved && validated.model?.parsed && validated.values) {
                payload.saved_equation_id = savedEquationSelect?.value ? parseInt(savedEquationSelect.value, 10) : null;
                payload.inputs = { ...validated.values, soil_type: validated.soilType || '' };
            } else {
                payload.saved_equation_id = null;
                payload.inputs = {
                    seawall: validated.seawall,
                    precipitation: validated.precipitation,
                    tropical_storm: validated.tropicalStorm,
                    floods: validated.floods,
                    soil_type: validated.soilType || '',
                };
            }
            saveCalculationToHistory(payload);
        }
    });

    btnRunNew?.addEventListener('click', () => {
        form.reset();
        showPlaceholder();
        if (resultContent) resultContent.setAttribute('hidden', '');
        lastCalculationContext = null;
        treeRecCache = null;
    });

    btnAskTocsea?.addEventListener('click', () => {
        if (!lastCalculationContext) return;
        const withContextUrl = document.getElementById('soilCalculatorPage')?.dataset?.askTocseaWithContextUrl || '/ask-tocsea/with-context';
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = withContextUrl;
        form.style.display = 'none';
        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        if (csrf) {
            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = '_token';
            csrfInput.value = csrf;
            form.appendChild(csrfInput);
        }
        const contextInput = document.createElement('input');
        contextInput.type = 'hidden';
        contextInput.name = 'calculation_context';
        contextInput.value = JSON.stringify(lastCalculationContext);
        form.appendChild(contextInput);
        document.body.appendChild(form);
        form.submit();
    });

    document.getElementById('btnModelDetails')?.addEventListener('click', (e) => {
        e.preventDefault();
        document.getElementById('how-it-works')?.scrollIntoView({ behavior: 'smooth' });
    });
});
