/**
 * TOCSEA Model Builder - Multiple linear regression UI
 */

document.addEventListener('DOMContentLoaded', () => {
    const btnRun = document.getElementById('btnRunRegression');
    const modelSelect = document.getElementById('modelSelect');
    const inputTable = document.getElementById('inputTable');
    const resultsEmpty = document.getElementById('resultsEmpty');
    const resultsLoading = document.getElementById('resultsLoading');
    const resultsContent = document.getElementById('resultsContent');
    const regressionError = document.getElementById('regressionError');
    const regressionEquationFormatted = document.getElementById('regressionEquationFormatted');
    const btnCopyEquation = document.getElementById('btnCopyEquation');
    const coefficientsTbody = document.querySelector('#coefficientsTable tbody');
    const metricR2 = document.getElementById('metricR2');
    const metricAdjR2 = document.getElementById('metricAdjR2');
    const metricRMSE = document.getElementById('metricRMSE');
    const metricMAE = document.getElementById('metricMAE');
    const metricR2Secondary = document.getElementById('metricR2Secondary');
    const metricRMSESecondary = document.getElementById('metricRMSESecondary');
    const metricMAESecondary = document.getElementById('metricMAESecondary');
    const pValueThresholdInput = document.getElementById('pValueThreshold');
    const generatedModelContent = document.getElementById('generatedModelContent');
    const noSignificantPredictors = document.getElementById('noSignificantPredictors');
    const significantPredictorsList = document.getElementById('significantPredictorsList');
    const btnSaveEquation = document.getElementById('btnSaveEquation');
    const btnUseModel = document.getElementById('btnUseModel');
    const btnDownloadReport = document.getElementById('btnDownloadReport');
    const btnRunNew = document.getElementById('btnRunNew');

    const COLUMNS = [
        'Year', 'Tropical_Storms', 'Tropical_Depression', 'Severe_Tropical_Storms', 'Typhoons', 'Super_Typhoons',
        'Floods', 'Storm_Surges', 'Precipitation_mm', 'Seawall_m', 'Vegetation_area_sqm',
        'Coastal_Elevation', 'Soil_loss_sqm', 'Remaining_Land_Area_sqm'
    ];

    const YEAR_MIN = 1900;
    const YEAR_MAX = 2100;

    let lastRegression = null;

    function collectRows() {
        const rows = [];
        const trs = inputTable.querySelectorAll('tbody tr');
        trs.forEach((tr) => {
            const row = {};
            COLUMNS.forEach((col) => {
                const input = tr.querySelector(`input[data-col="${col}"]`);
                row[col] = input?.value?.trim() ?? '';
            });
            rows.push(row);
        });
        return rows;
    }

    function clearCellErrors() {
        inputTable.querySelectorAll('.mb-input').forEach((el) => {
            el.classList.remove('is-invalid');
            el.removeAttribute('aria-invalid');
        });
    }

    function markCellInvalid(rowIndex, col) {
        const tr = inputTable.querySelector(`tbody tr[data-row="${String(rowIndex + 1)}"]`);
        if (!tr) return;
        const input = tr.querySelector(`input[data-col="${col}"]`);
        if (input) {
            input.classList.add('is-invalid');
            input.setAttribute('aria-invalid', 'true');
        }
    }

    function validateRows(rows) {
        clearCellErrors();
        const errors = [];
        let hasAnyTarget = false;
        rows.forEach((row, i) => {
            const target = row.Soil_loss_sqm;
            if (target && !isNaN(parseFloat(target))) hasAnyTarget = true;
            if (target && isNaN(parseFloat(target))) {
                errors.push(`Row ${i + 1}: Soil_loss_sqm must be numeric.`);
                markCellInvalid(i, 'Soil_loss_sqm');
            }
            if (row.Year) {
                const y = parseInt(row.Year, 10);
                if (isNaN(y) || y < YEAR_MIN || y > YEAR_MAX) {
                    errors.push(`Row ${i + 1}: Year must be between ${YEAR_MIN} and ${YEAR_MAX}.`);
                    markCellInvalid(i, 'Year');
                }
            }
            COLUMNS.forEach((col) => {
                if (col === 'Soil_loss_sqm' || col === 'Remaining_Land_Area_sqm' || col === 'Year') return;
                const v = row[col];
                if (v && isNaN(parseFloat(v))) {
                    errors.push(`Row ${i + 1}, ${col}: must be numeric.`);
                    markCellInvalid(i, col);
                }
            });
        });
        if (!hasAnyTarget) {
            errors.push('At least one row must have a numeric Soil_loss_sqm (target).');
        }
        let rowCount = 0;
        rows.forEach((r) => {
            if (r.Soil_loss_sqm && !isNaN(parseFloat(r.Soil_loss_sqm))) rowCount++;
        });
        if (rowCount < 10) {
            errors.push('At least 10 rows with valid Soil_loss_sqm are required for regression.');
        }
        return errors;
    }

    function showError(msg) {
        regressionError.textContent = msg;
        regressionError.hidden = false;
    }

    function hideError() {
        regressionError.hidden = true;
    }

    function setLoading(loading) {
        btnRun.disabled = loading;
        resultsEmpty.hidden = loading || !!lastRegression;
        resultsLoading.hidden = !loading;
        resultsContent.hidden = loading || !lastRegression;
    }

    async function runRegression() {
        const rows = collectRows();
        const errors = validateRows(rows);
        if (errors.length > 0) {
            showError(errors.join(' '));
            return;
        }
        clearCellErrors();
        hideError();
        setLoading(true);
        try {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const url = document.getElementById('modelBuilderPage')?.dataset?.runRegressionUrl || '/model-builder/run-regression';
            const res = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify({ rows }),
            });
            let data;
            try {
                data = await res.json();
            } catch (_) {
                throw new Error(res.status === 419 ? 'Session expired. Please refresh the page.' : 'Server error. Please try again.');
            }
            if (!res.ok) {
                throw new Error(data?.message || 'Regression failed.');
            }
            if (!data.success || !data.regression) {
                throw new Error(data?.message || 'Invalid response.');
            }
            lastRegression = data.regression;
            renderResults(data.regression);
            hideError();
        } catch (e) {
            showError(e.message || 'Request failed.');
        } finally {
            setLoading(false);
        }
    }

    /** Format number: 3 decimal places, comma-separated thousands */
    function formatNum(n) {
        if (typeof n !== 'number' || isNaN(n)) return '—';
        return n.toLocaleString('en-US', { minimumFractionDigits: 3, maximumFractionDigits: 3 });
    }

    /** Get current p-value threshold (default 0.05), clamped to valid range */
    function getPThreshold() {
        const el = pValueThresholdInput;
        if (!el) return 0.05;
        const v = parseFloat(el.value, 10);
        if (isNaN(v) || v < 0.01 || v > 0.5) return 0.05;
        return v;
    }

    /**
     * Build regression equation string from only significant predictors.
     * Intercept first; one predictor per line with + or −.
     * Format: Soil Loss = Intercept\n+ (β × Name)\n− (β × Name) ...
     */
    function buildDynamicEquation(reg, pThreshold) {
        const intercept = reg.intercept;
        const coefficients = reg.coefficients || {};
        const pValues = reg.p_values || {};
        const hasPValues = Object.keys(pValues).length > 0;
        const significant = hasPValues
            ? Object.keys(coefficients).filter((name) => {
                const p = pValues[name];
                return typeof p === 'number' && p < pThreshold;
            })
            : Object.keys(coefficients);
        const lines = ['Soil Loss = ' + formatNum(intercept)];
        significant.forEach((name) => {
            const beta = coefficients[name];
            if (typeof beta !== 'number') return;
            const absVal = formatNum(Math.abs(beta));
            const term = '(' + absVal + ' × ' + name + ')';
            lines.push(beta < 0 ? '− ' + term : '+ ' + term);
        });
        return lines.join('\n');
    }

    /** Get list of significant predictor names for current threshold */
    function getSignificantPredictors(reg, pThreshold) {
        const coefficients = reg.coefficients || {};
        const pValues = reg.p_values || {};
        const hasPValues = Object.keys(pValues).length > 0;
        if (!hasPValues) return Object.keys(coefficients);
        return Object.keys(coefficients).filter((name) => {
            const p = pValues[name];
            return typeof p === 'number' && p < pThreshold;
        });
    }

    /** Format equation: intercept on first line, each predictor on its own line; green for +, red for − */
    function formatEquation(equationStr) {
        if (!equationStr || equationStr === '—') return '<span class="mb-equation-placeholder">—</span>';
        const rawLines = equationStr.split('\n').map((s) => s.trim()).filter(Boolean);
        if (rawLines.length === 0) return '<span class="mb-equation-placeholder">—</span>';
        const htmlLines = [];
        htmlLines.push(`<div class="mb-equation-line mb-equation-intercept-line">${escapeHtml(rawLines[0])}</div>`);
        for (let i = 1; i < rawLines.length; i++) {
            const line = rawLines[i];
            const isNeg = line.startsWith('−') || line.startsWith('-');
            const cls = isNeg ? 'mb-equation-neg' : 'mb-equation-pos';
            htmlLines.push(`<div class="mb-equation-line mb-equation-term-line ${cls}">${escapeHtml(line)}</div>`);
        }
        return htmlLines.join('');
    }

    function renderEquation(equationStr) {
        if (!regressionEquationFormatted) return;
        regressionEquationFormatted.innerHTML = formatEquation(equationStr);
        regressionEquationFormatted.dataset.rawEquation = equationStr || '';
    }

    function renderResults(reg) {
        const pThreshold = getPThreshold();
        const equationStr = buildDynamicEquation(reg, pThreshold);
        const significantList = getSignificantPredictors(reg, pThreshold);
        const hasPValues = reg.p_values && Object.keys(reg.p_values).length > 0;
        const hasSignificant = significantList.length > 0;

        if (generatedModelContent) generatedModelContent.hidden = hasPValues && !hasSignificant;
        if (noSignificantPredictors) noSignificantPredictors.hidden = !hasPValues || hasSignificant;

        renderEquation(hasSignificant ? equationStr : null);

        if (significantPredictorsList) {
            significantPredictorsList.innerHTML = '';
            significantList.forEach((name) => {
                const li = document.createElement('li');
                const pVal = reg.p_values?.[name];
                const pStr = typeof pVal === 'number' ? pVal.toFixed(4) : '—';
                li.textContent = `${name} (p = ${pStr})`;
                significantPredictorsList.appendChild(li);
            });
        }

        const r2Str = reg.r_squared != null ? String(reg.r_squared) : '—';
        const adjR2Str = reg.adjusted_r_squared != null ? String(reg.adjusted_r_squared) : '—';
        const rmseStr = reg.rmse != null ? String(reg.rmse) : '—';
        const maeStr = reg.mae != null ? String(reg.mae) : '—';

        if (metricR2) metricR2.textContent = r2Str;
        if (metricAdjR2) metricAdjR2.textContent = adjR2Str;
        if (metricRMSE) metricRMSE.textContent = rmseStr;
        if (metricMAE) metricMAE.textContent = maeStr;
        if (metricR2Secondary) metricR2Secondary.textContent = r2Str;
        if (metricRMSESecondary) metricRMSESecondary.textContent = rmseStr;
        if (metricMAESecondary) metricMAESecondary.textContent = maeStr;

        coefficientsTbody.innerHTML = '';
        const pValues = reg.p_values || {};
        const stdErrors = reg.standard_errors || {};
        const tStats = reg.t_statistics || {};
        const coefs = { intercept: reg.intercept, ...reg.coefficients };
        Object.entries(coefs).forEach(([name, value]) => {
            const tr = document.createElement('tr');
            const se = stdErrors[name];
            const t = tStats[name];
            const p = pValues[name];
            const isSignificant = typeof p === 'number' && p < pThreshold;
            const coefCell = typeof value === 'number' ? value.toFixed(4) : String(value ?? '—');
            const seCell = typeof se === 'number' ? se.toFixed(4) : '—';
            const tCell = typeof t === 'number' ? t.toFixed(4) : '—';
            const pCell = typeof p === 'number' ? p.toFixed(4) : '—';
            const sigCell = name === 'intercept' ? '—' : (isSignificant ? 'Yes' : 'No');
            tr.innerHTML = `<td>${escapeHtml(name)}</td><td>${coefCell}</td><td>${seCell}</td><td>${tCell}</td><td>${pCell}</td><td>${escapeHtml(sigCell)}</td>`;
            coefficientsTbody.appendChild(tr);
        });

        resultsEmpty.hidden = true;
        resultsContent.hidden = false;
        resultsContent.querySelectorAll('.mb-fade-in').forEach((el, i) => {
            el.style.animationDelay = `${i * 0.08}s`;
        });
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    function escapeHtml(s) {
        const d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    /** Escape for HTML attribute value (e.g. data-formula) */
    function escapeHtmlAttr(s) {
        if (s == null) return '';
        const str = String(s);
        return str
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    const inputTableWrap = document.getElementById('inputTableWrap');

    /** Random int in [min, max] inclusive */
    function randInt(min, max) {
        return Math.floor(Math.random() * (max - min + 1)) + min;
    }

    /** Add random variation; result is always >= minVal (default 1, so no zeros) */
    function vary(val, pct = 0.18, minVal = 1) {
        if (typeof val !== 'number') return val;
        const delta = Math.max(1, Math.round(val * pct));
        const result = val + randInt(-delta, delta);
        return Math.max(minVal, result);
    }

    /** Briefly highlight an input to indicate value changed */
    function flashInput(input) {
        if (!input) return;
        input.classList.remove('mb-input-updated');
        void input.offsetWidth;
        input.classList.add('mb-input-updated');
        setTimeout(() => input.classList.remove('mb-input-updated'), 600);
    }

    /**
     * Generate random demo data: varied values without a fixed relationship.
     */
    function generateRandomDemoData() {
        const baseYear = new Date().getFullYear();
        const base = [
            { Year: baseYear - 19, Tropical_Storms: 2, Tropical_Depression: 1, Severe_Tropical_Storms: 1, Typhoons: 2, Super_Typhoons: 1, Floods: 2, Storm_Surges: 1, Precipitation_mm: 180, Seawall_m: 80, Vegetation_area_sqm: 4500, Coastal_Elevation: 3, Soil_loss_sqm: 52000, Remaining_Land_Area_sqm: 5000 },
            { Year: baseYear - 18, Tropical_Storms: 1, Tropical_Depression: 2, Severe_Tropical_Storms: 1, Typhoons: 1, Super_Typhoons: 1, Floods: 1, Storm_Surges: 1, Precipitation_mm: 120, Seawall_m: 150, Vegetation_area_sqm: 6000, Coastal_Elevation: 5, Soil_loss_sqm: 38000, Remaining_Land_Area_sqm: 8000 },
            { Year: baseYear - 17, Tropical_Storms: 3, Tropical_Depression: 1, Severe_Tropical_Storms: 1, Typhoons: 3, Super_Typhoons: 1, Floods: 3, Storm_Surges: 1, Precipitation_mm: 250, Seawall_m: 60, Vegetation_area_sqm: 3500, Coastal_Elevation: 2, Soil_loss_sqm: 61000, Remaining_Land_Area_sqm: 3000 },
            { Year: baseYear - 16, Tropical_Storms: 2, Tropical_Depression: 2, Severe_Tropical_Storms: 1, Typhoons: 1, Super_Typhoons: 1, Floods: 1, Storm_Surges: 1, Precipitation_mm: 90, Seawall_m: 200, Vegetation_area_sqm: 7500, Coastal_Elevation: 6, Soil_loss_sqm: 29000, Remaining_Land_Area_sqm: 9000 },
            { Year: baseYear - 15, Tropical_Storms: 4, Tropical_Depression: 2, Severe_Tropical_Storms: 1, Typhoons: 2, Super_Typhoons: 1, Floods: 2, Storm_Surges: 1, Precipitation_mm: 200, Seawall_m: 100, Vegetation_area_sqm: 5000, Coastal_Elevation: 4, Soil_loss_sqm: 48000, Remaining_Land_Area_sqm: 6000 },
            { Year: baseYear - 14, Tropical_Storms: 1, Tropical_Depression: 1, Severe_Tropical_Storms: 1, Typhoons: 1, Super_Typhoons: 1, Floods: 2, Storm_Surges: 1, Precipitation_mm: 150, Seawall_m: 120, Vegetation_area_sqm: 5500, Coastal_Elevation: 3, Soil_loss_sqm: 42000, Remaining_Land_Area_sqm: 7000 },
            { Year: baseYear - 13, Tropical_Storms: 2, Tropical_Depression: 1, Severe_Tropical_Storms: 1, Typhoons: 2, Super_Typhoons: 1, Floods: 1, Storm_Surges: 1, Precipitation_mm: 110, Seawall_m: 180, Vegetation_area_sqm: 6500, Coastal_Elevation: 5, Soil_loss_sqm: 35000, Remaining_Land_Area_sqm: 5500 },
            { Year: baseYear - 12, Tropical_Storms: 3, Tropical_Depression: 2, Severe_Tropical_Storms: 1, Typhoons: 1, Super_Typhoons: 1, Floods: 3, Storm_Surges: 1, Precipitation_mm: 220, Seawall_m: 70, Vegetation_area_sqm: 4000, Coastal_Elevation: 2, Soil_loss_sqm: 55000, Remaining_Land_Area_sqm: 4000 },
            { Year: baseYear - 11, Tropical_Storms: 2, Tropical_Depression: 1, Severe_Tropical_Storms: 1, Typhoons: 3, Super_Typhoons: 1, Floods: 2, Storm_Surges: 1, Precipitation_mm: 170, Seawall_m: 140, Vegetation_area_sqm: 5800, Coastal_Elevation: 4, Soil_loss_sqm: 40000, Remaining_Land_Area_sqm: 6500 },
            { Year: baseYear - 10, Tropical_Storms: 1, Tropical_Depression: 1, Severe_Tropical_Storms: 1, Typhoons: 1, Super_Typhoons: 1, Floods: 1, Storm_Surges: 1, Precipitation_mm: 140, Seawall_m: 90, Vegetation_area_sqm: 4800, Coastal_Elevation: 3, Soil_loss_sqm: 45000, Remaining_Land_Area_sqm: 5000 },
            { Year: baseYear - 9, Tropical_Storms: 2, Tropical_Depression: 2, Severe_Tropical_Storms: 1, Typhoons: 2, Super_Typhoons: 1, Floods: 2, Storm_Surges: 1, Precipitation_mm: 165, Seawall_m: 95, Vegetation_area_sqm: 5100, Coastal_Elevation: 4, Soil_loss_sqm: 47000, Remaining_Land_Area_sqm: 5400 },
            { Year: baseYear - 8, Tropical_Storms: 2, Tropical_Depression: 2, Severe_Tropical_Storms: 1, Typhoons: 1, Super_Typhoons: 1, Floods: 2, Storm_Surges: 1, Precipitation_mm: 130, Seawall_m: 170, Vegetation_area_sqm: 7200, Coastal_Elevation: 5, Soil_loss_sqm: 32000, Remaining_Land_Area_sqm: 8500 },
            { Year: baseYear - 7, Tropical_Storms: 4, Tropical_Depression: 1, Severe_Tropical_Storms: 1, Typhoons: 3, Super_Typhoons: 1, Floods: 3, Storm_Surges: 1, Precipitation_mm: 240, Seawall_m: 55, Vegetation_area_sqm: 3200, Coastal_Elevation: 2, Soil_loss_sqm: 64000, Remaining_Land_Area_sqm: 2800 },
            { Year: baseYear - 6, Tropical_Storms: 1, Tropical_Depression: 1, Severe_Tropical_Storms: 1, Typhoons: 1, Super_Typhoons: 1, Floods: 1, Storm_Surges: 1, Precipitation_mm: 85, Seawall_m: 210, Vegetation_area_sqm: 7800, Coastal_Elevation: 7, Soil_loss_sqm: 26000, Remaining_Land_Area_sqm: 9500 },
            { Year: baseYear - 5, Tropical_Storms: 3, Tropical_Depression: 2, Severe_Tropical_Storms: 1, Typhoons: 2, Super_Typhoons: 1, Floods: 2, Storm_Surges: 1, Precipitation_mm: 190, Seawall_m: 110, Vegetation_area_sqm: 5200, Coastal_Elevation: 4, Soil_loss_sqm: 46000, Remaining_Land_Area_sqm: 5800 },
            { Year: baseYear - 4, Tropical_Storms: 1, Tropical_Depression: 1, Severe_Tropical_Storms: 1, Typhoons: 2, Super_Typhoons: 1, Floods: 2, Storm_Surges: 1, Precipitation_mm: 155, Seawall_m: 125, Vegetation_area_sqm: 5600, Coastal_Elevation: 3, Soil_loss_sqm: 41000, Remaining_Land_Area_sqm: 7200 },
            { Year: baseYear - 3, Tropical_Storms: 2, Tropical_Depression: 2, Severe_Tropical_Storms: 1, Typhoons: 1, Super_Typhoons: 1, Floods: 1, Storm_Surges: 1, Precipitation_mm: 105, Seawall_m: 195, Vegetation_area_sqm: 6800, Coastal_Elevation: 5, Soil_loss_sqm: 34000, Remaining_Land_Area_sqm: 6000 },
            { Year: baseYear - 2, Tropical_Storms: 3, Tropical_Depression: 1, Severe_Tropical_Storms: 1, Typhoons: 2, Super_Typhoons: 1, Floods: 3, Storm_Surges: 1, Precipitation_mm: 215, Seawall_m: 65, Vegetation_area_sqm: 3900, Coastal_Elevation: 3, Soil_loss_sqm: 56000, Remaining_Land_Area_sqm: 4200 },
            { Year: baseYear - 1, Tropical_Storms: 2, Tropical_Depression: 2, Severe_Tropical_Storms: 1, Typhoons: 2, Super_Typhoons: 1, Floods: 2, Storm_Surges: 1, Precipitation_mm: 175, Seawall_m: 130, Vegetation_area_sqm: 5400, Coastal_Elevation: 4, Soil_loss_sqm: 43000, Remaining_Land_Area_sqm: 6200 },
            { Year: baseYear, Tropical_Storms: 1, Tropical_Depression: 1, Severe_Tropical_Storms: 1, Typhoons: 1, Super_Typhoons: 1, Floods: 1, Storm_Surges: 1, Precipitation_mm: 145, Seawall_m: 85, Vegetation_area_sqm: 4900, Coastal_Elevation: 3, Soil_loss_sqm: 44000, Remaining_Land_Area_sqm: 5100 },
        ];
        return base.map((row) => {
            const r = { ...row };
            COLUMNS.forEach((col) => {
                const v = r[col];
                if (typeof v === 'number') {
                    if (col === 'Year') {
                        r[col] = Math.max(YEAR_MIN, Math.min(YEAR_MAX, v + randInt(-1, 1)));
                    } else {
                        const pct = col === 'Coastal_Elevation' ? 0.15 : 0.18;
                        r[col] = vary(v, pct, 1);
                    }
                }
            });
            return r;
        });
    }

    /** Apply array of row objects to the input table; empty string for missing rows. */
    function applyExampleToTable(example) {
        inputTable.querySelectorAll('tbody tr').forEach((tr, i) => {
            const row = example[i] || {};
            COLUMNS.forEach((col) => {
                const input = tr.querySelector(`input[data-col="${col}"]`);
                if (input) {
                    const val = row[col];
                    const str = val !== undefined && val !== null ? String(val) : '';
                    if (str) {
                        input.value = str;
                        flashInput(input);
                    } else {
                        input.value = '';
                    }
                }
            });
        });
        clearCellErrors();
        if (inputTableWrap) {
            inputTableWrap.classList.remove('mb-load-animate');
            void inputTableWrap.offsetHeight;
            inputTableWrap.classList.add('mb-load-animate');
            setTimeout(() => inputTableWrap.classList.remove('mb-load-animate'), 500);
        }
    }

    /** Load example data into the table. */
    function loadExample() {
        applyExampleToTable(generateRandomDemoData());
    }

    function highlightInputForModel() {
        if (!inputTableWrap) return;
        if (modelSelect?.value === 'buguey') {
            inputTableWrap.classList.add('is-model-active');
        } else {
            inputTableWrap.classList.remove('is-model-active');
        }
    }

    function loadBugueyDefault() {
        const baseYear = new Date().getFullYear();
        const defaults = {
            Year: baseYear,
            Tropical_Storms: 2,
            Severe_Tropical_Storms: 1,
            Typhoons: 3,
            Super_Typhoons: 0,
            Floods: 2,
            Storm_Surges: 1,
            Precipitation_mm: 150,
            Seawall_m: 100,
            Vegetation_area_sqm: 5000,
            Coastal_Elevation: 5,
            Soil_loss_sqm: 45000,
            Remaining_Land_Area_sqm: 10000,
        };
        const rowCount = inputTable.querySelectorAll('tbody tr').length;
        inputTable.querySelectorAll('tbody tr').forEach((tr, i) => {
            Object.entries(defaults).forEach(([col, val]) => {
                const input = tr.querySelector(`input[data-col="${col}"]`);
                if (input) input.value = col === 'Year' ? baseYear - rowCount + 1 + i : (typeof val === 'number' ? val + (i > 0 ? i * 100 : 0) : val);
            });
        });
    }

    function onModelSelect() {
        const v = modelSelect.value;
        highlightInputForModel();
        if (v === 'buguey') loadBugueyDefault();
        if (lastRegression && v === 'buguey') {
            renderResults(lastRegression);
        }
    }

    function runNewCalculation() {
        inputTable.querySelectorAll('tbody input').forEach((i) => { i.value = ''; });
        clearCellErrors();
        lastRegression = null;
        resultsEmpty.hidden = false;
        resultsContent.hidden = true;
        hideError();
    }

    function downloadReport() {
        if (!lastRegression) return;
        const pThreshold = getPThreshold();
        const equationStr = buildDynamicEquation(lastRegression, pThreshold);
        const significantList = getSignificantPredictors(lastRegression, pThreshold);
        const lines = [
            'TOCSEA Model Builder - Regression Report',
            '=========================================',
            '',
            'Generated Regression Model (significant predictors only, p < ' + pThreshold + ')',
            '----------------------------------------------------------------',
            '',
            'Final Equation:',
            equationStr,
            '',
            'Significant Predictors:',
            significantList.length ? significantList.map((n) => '  - ' + n + ' (p = ' + (lastRegression.p_values?.[n] ?? '—') + ')').join('\n') : '  (none at this threshold)',
            '',
            'Model Summary:',
            '  R²: ' + (lastRegression.r_squared ?? '—'),
            '  Adjusted R²: ' + (lastRegression.adjusted_r_squared ?? '—'),
            '  RMSE: ' + (lastRegression.rmse ?? '—'),
            '  MAE: ' + (lastRegression.mae ?? '—'),
            '',
            'All Coefficients (with p-values):',
            '  intercept: ' + lastRegression.intercept,
            ...Object.entries(lastRegression.coefficients || {}).map(([k, v]) => {
                const p = lastRegression.p_values?.[k];
                const se = lastRegression.standard_errors?.[k];
                const t = lastRegression.t_statistics?.[k];
                return '  ' + k + ': coef = ' + v + (se != null ? ', SE = ' + se : '') + (t != null ? ', t = ' + t : '') + (p != null ? ', p = ' + p : '');
            }),
        ];
        const blob = new Blob([lines.join('\n')], { type: 'text/plain' });
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = `tocsea-regression-${new Date().toISOString().slice(0, 10)}.txt`;
        a.click();
        URL.revokeObjectURL(a.href);
    }

    function validateCell(input) {
        const col = input.dataset.col;
        const val = input.value?.trim() ?? '';
        let valid = true;
        if (col === 'Year' && val) {
            const y = parseInt(val, 10);
            valid = !isNaN(y) && y >= YEAR_MIN && y <= YEAR_MAX;
        } else if (col !== 'Remaining_Land_Area_sqm' && val) {
            valid = !isNaN(parseFloat(val));
        }
        input.classList.toggle('is-invalid', !valid);
        input.setAttribute('aria-invalid', valid ? 'false' : 'true');
    }

    inputTable?.addEventListener('blur', (e) => {
        if (e.target?.classList?.contains('mb-input')) validateCell(e.target);
    }, true);

    inputTable?.addEventListener('input', (e) => {
        if (e.target?.classList?.contains('mb-input') && e.target.classList.contains('is-invalid')) {
            validateCell(e.target);
        }
    }, true);

    document.getElementById('btnLoadExample')?.addEventListener('click', loadExample);

    btnCopyEquation?.addEventListener('click', () => {
        const raw = regressionEquationFormatted?.dataset?.rawEquation?.trim();
        if (!raw) return;
        navigator.clipboard?.writeText(raw).then(() => {
            const label = btnCopyEquation.querySelector('.lucide-icon')?.nextSibling;
            const orig = btnCopyEquation.innerHTML;
            btnCopyEquation.innerHTML = '<i data-lucide="check" class="lucide-icon lucide-icon-sm" aria-hidden="true"></i> Copied!';
            if (typeof lucide !== 'undefined') lucide.createIcons();
            setTimeout(() => {
                btnCopyEquation.innerHTML = orig;
                if (typeof lucide !== 'undefined') lucide.createIcons();
            }, 1500);
        });
    });
    pValueThresholdInput?.addEventListener('change', () => {
        if (lastRegression) renderResults(lastRegression);
    });
    pValueThresholdInput?.addEventListener('input', () => {
        if (lastRegression) renderResults(lastRegression);
    });

    btnRun?.addEventListener('click', runRegression);
    modelSelect?.addEventListener('change', onModelSelect);

    highlightInputForModel();

    // --- Save Equation modal & saved equations table ---
    const saveEquationModal = document.getElementById('saveEquationModal');
    const saveEquationName = document.getElementById('saveEquationName');
    const saveEquationFormula = document.getElementById('saveEquationFormula');
    const saveEquationNameError = document.getElementById('saveEquationNameError');
    const saveEquationFormError = document.getElementById('saveEquationFormError');
    const saveEquationForm = document.getElementById('saveEquationForm');
    const saveEquationSubmit = document.getElementById('saveEquationSubmit');
    const saveEquationModalClose = document.getElementById('saveEquationModalClose');
    const saveEquationCancel = document.getElementById('saveEquationCancel');
    const savedEquationsEmpty = document.getElementById('savedEquationsEmpty');
    const savedEquationsTableWrap = document.getElementById('savedEquationsTableWrap');
    const savedEquationsTableBody = document.getElementById('savedEquationsTableBody');
    const savedEquationsLoading = document.getElementById('savedEquationsLoading');
    const savedEquationsPagination = document.getElementById('savedEquationsPagination');

    let currentSavedPage = 1;

    function getSavedEquationsUrls() {
        const page = document.getElementById('modelBuilderPage');
        const base = page?.dataset?.savedEquationsBaseUrl || '/saved-equations';
        return {
            index: page?.dataset?.savedEquationsIndexUrl || base,
            store: page?.dataset?.savedEquationsStoreUrl || base,
            base,
        };
    }

    function openSaveEquationModal() {
        const raw = regressionEquationFormatted?.dataset?.rawEquation?.trim();
        if (!raw) {
            alert('Run a regression first to generate an equation, then click Save Equation.');
            return;
        }
        if (saveEquationName) saveEquationName.value = '';
        if (saveEquationFormula) saveEquationFormula.value = raw;
        if (saveEquationNameError) { saveEquationNameError.hidden = true; saveEquationNameError.textContent = ''; }
        if (saveEquationFormError) { saveEquationFormError.hidden = true; saveEquationFormError.textContent = ''; }
        saveEquationName?.classList.remove('is-invalid');
        if (saveEquationModal) {
            saveEquationModal.hidden = false;
            saveEquationModal.setAttribute('aria-hidden', 'false');
            saveEquationName?.focus();
        }
    }

    function closeSaveEquationModal() {
        if (saveEquationModal) {
            saveEquationModal.hidden = true;
            saveEquationModal.setAttribute('aria-hidden', 'true');
        }
    }

    function formatSavedDate(iso) {
        if (!iso) return '—';
        const d = new Date(iso);
        return isNaN(d.getTime()) ? '—' : d.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
    }

    function renderSavedEquationsPagination(pagination) {
        if (!savedEquationsPagination || !pagination) return;
        const { current_page, last_page } = pagination;
        if (last_page <= 1) {
            savedEquationsPagination.hidden = true;
            savedEquationsPagination.innerHTML = '';
            return;
        }
        savedEquationsPagination.hidden = false;
        const parts = [];
        if (current_page > 1) {
            parts.push(`<button type="button" class="mb-saved-page-btn" data-page="${current_page - 1}" aria-label="Previous page">Previous</button>`);
        }
        parts.push(`<span class="mb-saved-page-info">Page ${current_page} of ${last_page}</span>`);
        if (current_page < last_page) {
            parts.push(`<button type="button" class="mb-saved-page-btn" data-page="${current_page + 1}" aria-label="Next page">Next</button>`);
        }
        savedEquationsPagination.innerHTML = parts.join('');
        savedEquationsPagination.querySelectorAll('.mb-saved-page-btn').forEach((btn) => {
            btn.addEventListener('click', () => {
                currentSavedPage = parseInt(btn.dataset.page, 10);
                loadSavedEquationsTable(currentSavedPage);
            });
        });
    }

    async function loadSavedEquationsTable(page = 1) {
        currentSavedPage = page;
        const urls = getSavedEquationsUrls();
        const url = `${urls.index}${urls.index.includes('?') ? '&' : '?'}page=${page}`;
        if (savedEquationsLoading) { savedEquationsLoading.hidden = false; }
        if (savedEquationsEmpty) savedEquationsEmpty.hidden = true;
        try {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const res = await fetch(url, {
                method: 'GET',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf || '' },
            });
            const data = await res.json().catch(() => ({}));
            const list = data?.equations || [];
            const pagination = data?.pagination;

            if (savedEquationsTableBody) {
                savedEquationsTableBody.innerHTML = '';
                list.forEach((eq) => {
                    const tr = document.createElement('tr');
                    const formulaText = (eq.formula || '').replace(/\s+/g, ' ').trim();
                    const formulaDisplay = formulaText.length > 120 ? formulaText.slice(0, 120) + '…' : formulaText;
                    const createdStr = formatSavedDate(eq.created_at);
                    const updatedStr = formatSavedDate(eq.updated_at);
                    tr.innerHTML = `
                        <td class="mb-saved-name-cell">${escapeHtml(eq.equation_name)}</td>
                        <td class="mb-saved-formula-cell" title="${escapeHtml(formulaText)}">${escapeHtml(formulaDisplay)}</td>
                        <td class="mb-saved-date-cell">${escapeHtml(createdStr)}</td>
                        <td class="mb-saved-date-cell">${escapeHtml(updatedStr)}</td>
                        <td class="mb-saved-actions-cell">
                            <button type="button" class="mb-saved-action-btn mb-saved-action-edit" data-id="${escapeHtml(String(eq.id))}" data-name="${escapeHtml(eq.equation_name)}" data-formula="${escapeHtmlAttr(eq.formula || '')}" aria-label="Edit">Edit</button>
                            <span class="mb-saved-action-sep" aria-hidden="true">|</span>
                            <button type="button" class="mb-saved-action-btn mb-saved-action-delete" data-id="${escapeHtml(String(eq.id))}" data-name="${escapeHtml(eq.equation_name)}" aria-label="Delete">Delete</button>
                        </td>
                    `;
                    savedEquationsTableBody.appendChild(tr);
                });
            }

            const hasRows = list.length > 0;
            if (savedEquationsEmpty) {
                savedEquationsEmpty.textContent = pagination?.total === 0 ? 'No saved equations yet. Run a regression and click Save Equation to store one.' : 'No equations on this page.';
                savedEquationsEmpty.hidden = hasRows;
            }
            if (savedEquationsTableWrap) savedEquationsTableWrap.hidden = !hasRows;
            renderSavedEquationsPagination(pagination);
        } catch (_) {
            if (savedEquationsEmpty) {
                savedEquationsEmpty.textContent = 'Unable to load saved equations.';
                savedEquationsEmpty.hidden = false;
            }
            if (savedEquationsTableWrap) savedEquationsTableWrap.hidden = true;
            if (savedEquationsPagination) { savedEquationsPagination.hidden = true; savedEquationsPagination.innerHTML = ''; }
        } finally {
            if (savedEquationsLoading) savedEquationsLoading.hidden = true;
        }
    }

    function openEditEquationModal(id, name, formula) {
        const editModal = document.getElementById('editEquationModal');
        const editId = document.getElementById('editEquationId');
        const editName = document.getElementById('editEquationName');
        const editFormula = document.getElementById('editEquationFormula');
        const editNameError = document.getElementById('editEquationNameError');
        const editFormError = document.getElementById('editEquationFormError');
        if (editId) editId.value = id;
        if (editName) { editName.value = name || ''; editName.classList.remove('is-invalid'); }
        if (editFormula) editFormula.value = formula || '';
        if (editNameError) { editNameError.hidden = true; editNameError.textContent = ''; }
        if (editFormError) { editFormError.hidden = true; editFormError.textContent = ''; }
        if (editModal) {
            editModal.hidden = false;
            editModal.setAttribute('aria-hidden', 'false');
            editName?.focus();
        }
    }

    function closeEditEquationModal() {
        const editModal = document.getElementById('editEquationModal');
        if (editModal) {
            editModal.hidden = true;
            editModal.setAttribute('aria-hidden', 'true');
        }
    }

    let deleteEquationId = null;

    function openDeleteEquationModal(id, name) {
        deleteEquationId = id;
        const msg = document.getElementById('deleteEquationMessage');
        if (msg) msg.textContent = `Are you sure you want to delete "${name || 'this equation'}"? This cannot be undone.`;
        const modal = document.getElementById('deleteEquationModal');
        if (modal) {
            modal.hidden = false;
            modal.setAttribute('aria-hidden', 'false');
        }
    }

    function closeDeleteEquationModal() {
        deleteEquationId = null;
        const modal = document.getElementById('deleteEquationModal');
        if (modal) {
            modal.hidden = true;
            modal.setAttribute('aria-hidden', 'true');
        }
    }

    saveEquationForm?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const name = saveEquationName?.value?.trim() || '';
        const formula = saveEquationFormula?.value?.trim() || '';
        if (saveEquationNameError) { saveEquationNameError.hidden = true; saveEquationNameError.textContent = ''; }
        if (saveEquationFormError) { saveEquationFormError.hidden = true; saveEquationFormError.textContent = ''; }
        saveEquationName?.classList.remove('is-invalid');
        if (!name) {
            saveEquationName?.classList.add('is-invalid');
            if (saveEquationNameError) { saveEquationNameError.textContent = 'Please enter an equation name.'; saveEquationNameError.hidden = false; }
            return;
        }
        if (saveEquationSubmit) saveEquationSubmit.disabled = true;
        const urls = getSavedEquationsUrls();
        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        try {
            const res = await fetch(urls.store, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf || '',
                },
                body: JSON.stringify({ equation_name: name, formula }),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
                const msg = data?.message || data?.errors?.equation_name?.[0] || 'Failed to save equation.';
                if (data?.errors?.equation_name) {
                    saveEquationName?.classList.add('is-invalid');
                    if (saveEquationNameError) { saveEquationNameError.textContent = msg; saveEquationNameError.hidden = false; }
                } else {
                    if (saveEquationFormError) { saveEquationFormError.textContent = msg; saveEquationFormError.hidden = false; }
                }
            } else {
                closeSaveEquationModal();
                await loadSavedEquationsTable();
            }
        } catch (_) {
            if (saveEquationFormError) { saveEquationFormError.textContent = 'Network error. Please try again.'; saveEquationFormError.hidden = false; }
        } finally {
            if (saveEquationSubmit) saveEquationSubmit.disabled = false;
        }
    });

    btnSaveEquation?.addEventListener('click', openSaveEquationModal);
    saveEquationModalClose?.addEventListener('click', closeSaveEquationModal);
    saveEquationCancel?.addEventListener('click', closeSaveEquationModal);
    saveEquationModal?.addEventListener('click', (e) => { if (e.target === saveEquationModal) closeSaveEquationModal(); });
    saveEquationModal?.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeSaveEquationModal(); });

    savedEquationsTableBody?.addEventListener('click', (e) => {
        const editBtn = e.target.closest('.mb-saved-action-edit');
        const deleteBtn = e.target.closest('.mb-saved-action-delete');
        if (editBtn) {
            const id = editBtn.dataset.id;
            const name = editBtn.dataset.name ?? '';
            const formula = editBtn.dataset.formula ?? '';
            openEditEquationModal(id, name, formula);
        } else if (deleteBtn) {
            openDeleteEquationModal(deleteBtn.dataset.id, deleteBtn.dataset.name ?? '');
        }
    });

    document.getElementById('editEquationForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const editId = document.getElementById('editEquationId')?.value?.trim();
        const editName = document.getElementById('editEquationName')?.value?.trim();
        const editFormula = document.getElementById('editEquationFormula')?.value?.trim();
        const editNameError = document.getElementById('editEquationNameError');
        const editFormError = document.getElementById('editEquationFormError');
        const editSubmit = document.getElementById('editEquationSubmit');
        if (editNameError) { editNameError.hidden = true; editNameError.textContent = ''; }
        if (editFormError) { editFormError.hidden = true; editFormError.textContent = ''; }
        document.getElementById('editEquationName')?.classList.remove('is-invalid');
        if (!editId || !editName) {
            if (editNameError) { editNameError.textContent = 'Please enter an equation name.'; editNameError.hidden = false; }
            document.getElementById('editEquationName')?.classList.add('is-invalid');
            return;
        }
        if (!editFormula) {
            if (editFormError) { editFormError.textContent = 'Formula is required.'; editFormError.hidden = false; }
            return;
        }
        if (editSubmit) editSubmit.disabled = true;
        const urls = getSavedEquationsUrls();
        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        try {
            const res = await fetch(`${urls.base}/${editId}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf || '',
                    'X-HTTP-Method-Override': 'PUT',
                },
                body: JSON.stringify({ equation_name: editName, formula: editFormula }),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
                const msg = data?.message || data?.errors?.equation_name?.[0] || 'Failed to update equation.';
                if (data?.errors?.equation_name) {
                    document.getElementById('editEquationName')?.classList.add('is-invalid');
                    if (editNameError) { editNameError.textContent = msg; editNameError.hidden = false; }
                } else {
                    if (editFormError) { editFormError.textContent = msg; editFormError.hidden = false; }
                }
            } else {
                closeEditEquationModal();
                await loadSavedEquationsTable(currentSavedPage);
            }
        } catch (_) {
            if (editFormError) { editFormError.textContent = 'Network error. Please try again.'; editFormError.hidden = false; }
        } finally {
            if (editSubmit) editSubmit.disabled = false;
        }
    });

    document.getElementById('deleteEquationConfirm')?.addEventListener('click', async () => {
        if (deleteEquationId == null) return;
        const urls = getSavedEquationsUrls();
        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        const btn = document.getElementById('deleteEquationConfirm');
        if (btn) btn.disabled = true;
        try {
            const res = await fetch(`${urls.base}/${deleteEquationId}`, {
                method: 'DELETE',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf || '', 'X-HTTP-Method-Override': 'DELETE' },
            });
            const data = await res.json().catch(() => ({}));
            if (res.ok && data?.success) {
                closeDeleteEquationModal();
                await loadSavedEquationsTable(currentSavedPage);
            } else {
                alert(data?.message || 'Failed to delete equation.');
            }
        } catch (_) {
            alert('Network error. Please try again.');
        } finally {
            if (btn) btn.disabled = false;
        }
    });

    document.getElementById('editEquationModalClose')?.addEventListener('click', closeEditEquationModal);
    document.getElementById('editEquationCancel')?.addEventListener('click', closeEditEquationModal);
    document.getElementById('editEquationModal')?.addEventListener('click', (e) => { if (e.target?.id === 'editEquationModal') closeEditEquationModal(); });
    document.getElementById('editEquationModal')?.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeEditEquationModal(); });

    document.getElementById('deleteEquationModalClose')?.addEventListener('click', closeDeleteEquationModal);
    document.getElementById('deleteEquationCancel')?.addEventListener('click', closeDeleteEquationModal);
    document.getElementById('deleteEquationModal')?.addEventListener('click', (e) => { if (e.target?.id === 'deleteEquationModal') closeDeleteEquationModal(); });
    document.getElementById('deleteEquationModal')?.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeDeleteEquationModal(); });

    loadSavedEquationsTable();

    btnUseModel?.addEventListener('click', () => alert('Use Model: Coming soon.'));
    btnDownloadReport?.addEventListener('click', downloadReport);
    btnRunNew?.addEventListener('click', runNewCalculation);

    if (typeof lucide !== 'undefined') lucide.createIcons();
});
