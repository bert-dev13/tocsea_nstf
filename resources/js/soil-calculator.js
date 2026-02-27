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
    const infoModelName = document.getElementById('infoModelName');
    const infoFormulaDisplay = document.getElementById('infoFormulaDisplay');
    const infoFormulaBreakdown = document.getElementById('infoFormulaBreakdown');

    let savedEquationsList = [];

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
    }

    function showResult() {
        if (resultEmpty) resultEmpty.setAttribute('hidden', '');
        if (resultContent) resultContent.removeAttribute('hidden');
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

    function initInputWatchers() {
        function maybeShowPlaceholder() {
            if (areInputsEmpty() && resultContent && !resultContent.hasAttribute('hidden')) {
                showPlaceholder();
                resultContent.setAttribute('hidden', '');
            }
        }
        inputsToWatch.forEach((id) => {
            const el = document.getElementById(id);
            el?.addEventListener('input', maybeShowPlaceholder);
            el?.addEventListener('change', maybeShowPlaceholder);
        });
        form?.addEventListener('input', (e) => {
            if (e.target?.dataset?.variable) maybeShowPlaceholder();
        });
        form?.addEventListener('change', (e) => {
            if (e.target?.dataset?.variable) maybeShowPlaceholder();
        });
    }

    savedEquationSelect?.addEventListener('change', onSavedEquationChange);
    loadSavedEquations().then(() => {
        onSavedEquationChange();
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
        document.querySelectorAll('#soilCalculatorForm input, #soilCalculatorForm select').forEach(el => el.classList.remove('is-invalid'));
    }

    function showError(fieldId, message) {
        const input = document.getElementById(fieldId);
        const errorEl = document.getElementById(fieldId + '-error');
        if (input) input.classList.add('is-invalid');
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
                valid = false;
            } else {
                const values = {};
                parsed.variables.forEach((varName) => {
                    const input = document.querySelector(`input[data-variable="${varName}"]`);
                    const val = input?.value?.trim();
                    const num = val === '' ? NaN : parseFloat(val);
                    if (Number.isNaN(num)) {
                        showError(input?.id || 'saved_equation', `Enter a valid number for ${varName}.`);
                        valid = false;
                    } else {
                        values[varName] = num;
                    }
                });
                if (valid) return { useSaved: true, model: { name: eq.equation_name, parsed }, values, soilType: soilType || 'loamy' };
            }
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
        if (!validated) return;

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
        if (resultValue) resultValue.textContent = Number.isNaN(soilLoss) ? '—' : soilLoss.toLocaleString('en-US', { maximumFractionDigits: 2, minimumFractionDigits: 2 });
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
    });

    document.getElementById('btnModelDetails')?.addEventListener('click', (e) => {
        e.preventDefault();
        document.getElementById('how-it-works')?.scrollIntoView({ behavior: 'smooth' });
    });
});
