/**
 * TOCSEA Model Builder - Multiple linear regression UI
 * Lucide: import icons and createIcons so SVG renders (no global dependency).
 */

import {
    createIcons,
    Box,
    Bookmark,
    Download,
    Eraser,
    Play,
    BarChart2,
    Copy,
    Save,
    RefreshCw,
    Check,
    CheckCircle,
} from 'lucide';

const MB_ICONS = { Box, Bookmark, Download, Eraser, Play, BarChart2, Copy, Save, RefreshCw, Check, CheckCircle };

const MB_SAVE_SUCCESS_FLAG = 'mbEquationSavedSuccess';

if (typeof sessionStorage !== 'undefined' && sessionStorage.getItem(MB_SAVE_SUCCESS_FLAG) === '1') {
    if (typeof history !== 'undefined' && history.scrollRestoration) {
        history.scrollRestoration = 'manual';
    }
}

function createMbIcons() {
    createIcons({ icons: MB_ICONS, nameAttr: 'data-lucide' });
}

document.addEventListener('DOMContentLoaded', () => {
    const CHECK_CIRCLE_SVG = '<svg class="mb-save-toast-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>';
    const TOAST_DURATION_MS = 2500;
    let mbToastDismissTimeout = null;

    function showToast(message) {
        let wrap = document.getElementById('mbSaveToastWrap');
        if (!wrap) {
            wrap = document.createElement('div');
            wrap.id = 'mbSaveToastWrap';
            wrap.className = 'mb-save-toast-wrap';
            wrap.setAttribute('aria-live', 'polite');
            wrap.setAttribute('aria-atomic', 'true');
            document.body.appendChild(wrap);
        }
        let toast = wrap.querySelector('.mb-save-toast');
        if (!toast) {
            toast = document.createElement('div');
            toast.className = 'mb-save-toast';
            wrap.appendChild(toast);
        }
        if (mbToastDismissTimeout) {
            clearTimeout(mbToastDismissTimeout);
            mbToastDismissTimeout = null;
        }
        toast.innerHTML = CHECK_CIRCLE_SVG + '<span>' + String(message).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</span>';
        toast.classList.remove('mb-save-toast-visible');
        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                toast.classList.add('mb-save-toast-visible');
            });
        });
        mbToastDismissTimeout = setTimeout(() => {
            toast.classList.remove('mb-save-toast-visible');
            mbToastDismissTimeout = null;
        }, TOAST_DURATION_MS);
    }

    function showSaveSuccessToastOnLoad() {
        window.scrollTo(0, 0);
        document.documentElement.scrollTop = 0;
        document.body.scrollTop = 0;
        showToast('Equation saved successfully.');
    }

    try {
        if (sessionStorage.getItem(MB_SAVE_SUCCESS_FLAG) === '1') {
            if (typeof history !== 'undefined' && history.scrollRestoration) {
                history.scrollRestoration = 'manual';
            }
            window.scrollTo(0, 0);
            document.documentElement.scrollTop = 0;
            document.body.scrollTop = 0;
            sessionStorage.removeItem(MB_SAVE_SUCCESS_FLAG);
            requestAnimationFrame(() => showSaveSuccessToastOnLoad());
        }
    } catch (_) { /* ignore */ }

    const btnRun = document.getElementById('btnRunRegression');
    const inputTable = document.getElementById('inputTable');
    const resultsEmpty = document.getElementById('resultsEmpty');
    const resultsLoading = document.getElementById('resultsLoading');
    const resultsContent = document.getElementById('resultsContent');
    const regressionError = document.getElementById('regressionError');
    const regressionWarnings = document.getElementById('regressionWarnings');
    const regressionEquationFormatted = document.getElementById('regressionEquationFormatted');
    const btnCopyEquation = document.getElementById('btnCopyEquation');
    const coefficientsTbody = document.querySelector('#coefficientsTable tbody');
    const metricR2 = document.getElementById('metricR2');
    const metricAdjR2 = document.getElementById('metricAdjR2');
    const metricR2Secondary = document.getElementById('metricR2Secondary');
    const pValueThresholdInput = document.getElementById('pValueThreshold');
    const generatedModelContent = document.getElementById('generatedModelContent');
    const noSignificantPredictors = document.getElementById('noSignificantPredictors');
    const significantPredictorsList = document.getElementById('significantPredictorsList');
    const btnSaveEquation = document.getElementById('btnSaveEquation');
    const btnRunNew = document.getElementById('btnRunNew');

    const COLUMNS = [
        'Year', 'Tropical_Depression', 'Tropical_Storms', 'Severe_Tropical_Storms', 'Typhoons', 'Super_Typhoons',
        'Floods', 'Storm_Surges', 'Precipitation_mm', 'Seawall_m', 'Vegetation_area_sqm',
        'Coastal_Elevation', 'Soil_loss_sqm'
    ];

    /** Predictor columns (all except Year and Soil_loss_sqm) — required for a row to be "complete". */
    const PREDICTOR_COLUMNS = COLUMNS.filter((c) => c !== 'Year' && c !== 'Soil_loss_sqm');

    /** Full predictor list in display order for All Coefficients table (11 predictors). Always show all; missing ones display as "Not estimated (dropped)". */
    const FULL_PREDICTOR_LIST = [
        'Tropical_Depression',
        'Tropical_Storms',
        'Severe_Tropical_Storms',
        'Typhoons',
        'Super_Typhoons',
        'Floods',
        'Storm_Surges',
        'Precipitation_mm',
        'Seawall_m',
        'Vegetation_area_sqm',
        'Coastal_Elevation',
    ];

    /** Minimum number of rows with valid Soil_Loss_Sqm and complete predictor data to run regression. */
    const MIN_VALID_ROWS = 5;

    /** Default dataset (2015–2024) shown when user opens the Model Builder page. */
    const DEFAULT_INPUT_DATA = [
        { Year: 2024, Tropical_Depression: 3, Tropical_Storms: 2, Severe_Tropical_Storms: 1, Typhoons: 1, Super_Typhoons: 0, Floods: 2, Storm_Surges: 1, Precipitation_mm: 2107, Seawall_m: 1155, Vegetation_area_sqm: 1914937, Coastal_Elevation: 2.00, Soil_loss_sqm: 28805.02 },
        { Year: 2023, Tropical_Depression: 1, Tropical_Storms: 2, Severe_Tropical_Storms: 0, Typhoons: 0, Super_Typhoons: 0, Floods: 1, Storm_Surges: 0, Precipitation_mm: 1992, Seawall_m: 1155, Vegetation_area_sqm: 1939450, Coastal_Elevation: 2.00, Soil_loss_sqm: 24512.55 },
        { Year: 2022, Tropical_Depression: 1, Tropical_Storms: 1, Severe_Tropical_Storms: 1, Typhoons: 0, Super_Typhoons: 1, Floods: 2, Storm_Surges: 0, Precipitation_mm: 2004, Seawall_m: 945, Vegetation_area_sqm: 1975836, Coastal_Elevation: 2.02, Soil_loss_sqm: 36386.46 },
        { Year: 2021, Tropical_Depression: 0, Tropical_Storms: 3, Severe_Tropical_Storms: 0, Typhoons: 0, Super_Typhoons: 0, Floods: 2, Storm_Surges: 0, Precipitation_mm: 1601, Seawall_m: 540, Vegetation_area_sqm: 2034078, Coastal_Elevation: 2.02, Soil_loss_sqm: 58241.24 },
        { Year: 2020, Tropical_Depression: 4, Tropical_Storms: 0, Severe_Tropical_Storms: 0, Typhoons: 0, Super_Typhoons: 0, Floods: 1, Storm_Surges: 0, Precipitation_mm: 1558, Seawall_m: 0, Vegetation_area_sqm: 2116879, Coastal_Elevation: 2.04, Soil_loss_sqm: 82801.55 },
        { Year: 2019, Tropical_Depression: 3, Tropical_Storms: 1, Severe_Tropical_Storms: 1, Typhoons: 0, Super_Typhoons: 0, Floods: 2, Storm_Surges: 0, Precipitation_mm: 1889, Seawall_m: 0, Vegetation_area_sqm: 2212174, Coastal_Elevation: 2.04, Soil_loss_sqm: 95294.58 },
        { Year: 2018, Tropical_Depression: 2, Tropical_Storms: 1, Severe_Tropical_Storms: 0, Typhoons: 1, Super_Typhoons: 0, Floods: 1, Storm_Surges: 0, Precipitation_mm: 1891, Seawall_m: 0, Vegetation_area_sqm: 2302958, Coastal_Elevation: 2.05, Soil_loss_sqm: 90784.39 },
        { Year: 2017, Tropical_Depression: 2, Tropical_Storms: 2, Severe_Tropical_Storms: 0, Typhoons: 0, Super_Typhoons: 0, Floods: 1, Storm_Surges: 0, Precipitation_mm: 1647, Seawall_m: 0, Vegetation_area_sqm: 2390654, Coastal_Elevation: 2.08, Soil_loss_sqm: 87695.70 },
        { Year: 2016, Tropical_Depression: 1, Tropical_Storms: 2, Severe_Tropical_Storms: 0, Typhoons: 0, Super_Typhoons: 1, Floods: 2, Storm_Surges: 1, Precipitation_mm: 1877, Seawall_m: 0, Vegetation_area_sqm: 2485590, Coastal_Elevation: 2.08, Soil_loss_sqm: 94936.12 },
        { Year: 2015, Tropical_Depression: 3, Tropical_Storms: 2, Severe_Tropical_Storms: 0, Typhoons: 1, Super_Typhoons: 0, Floods: 2, Storm_Surges: 1, Precipitation_mm: 1978, Seawall_m: 0, Vegetation_area_sqm: 2582260, Coastal_Elevation: 2.10, Soil_loss_sqm: 96670 },
    ];

    const YEAR_MIN = 1900;
    const YEAR_MAX = 2100;

    /** Paper-based regression: default model from the research paper (static values from the study). All 11 predictors have numeric values so the table never shows blanks. */
    const PAPER_EQUATION_STR = 'Soil Loss = 49,218.016 - 61.646(Seawall) + 19.931(Precipitation) + 1779.250(Tropical Storm) + 2489.243(Flood)';
    const PAPER_REGRESSION = {
        intercept: 49218.016,
        coefficients: {
            Tropical_Depression: 0,
            Tropical_Storms: 1779.250,
            Severe_Tropical_Storms: 0,
            Typhoons: 0,
            Super_Typhoons: 0,
            Floods: 2489.243,
            Storm_Surges: 0,
            Precipitation_mm: 19.931,
            Seawall_m: -61.646,
            Vegetation_area_sqm: 0,
            Coastal_Elevation: 0,
        },
        p_values: {
            intercept: 0,
            Tropical_Depression: 1,
            Tropical_Storms: 0.027,
            Severe_Tropical_Storms: 1,
            Typhoons: 1,
            Super_Typhoons: 1,
            Floods: 0.026,
            Storm_Surges: 1,
            Precipitation_mm: 0.014,
            Seawall_m: 0,
            Vegetation_area_sqm: 1,
            Coastal_Elevation: 1,
        },
        standard_errors: {
            intercept: 4136.960,
            Tropical_Depression: 0.0001,
            Tropical_Storms: 476.911,
            Severe_Tropical_Storms: 0.0001,
            Typhoons: 0.0001,
            Super_Typhoons: 0.0001,
            Floods: 805.221,
            Storm_Surges: 0.0001,
            Precipitation_mm: 2.419,
            Seawall_m: 0.849,
            Vegetation_area_sqm: 0.0001,
            Coastal_Elevation: 0.0001,
        },
        t_statistics: {
            intercept: 11.90,
            Tropical_Depression: 0,
            Tropical_Storms: 3.73,
            Severe_Tropical_Storms: 0,
            Typhoons: 0,
            Super_Typhoons: 0,
            Floods: 3.09,
            Storm_Surges: 0,
            Precipitation_mm: 8.24,
            Seawall_m: -72.61,
            Vegetation_area_sqm: 0,
            Coastal_Elevation: 0,
        },
        r_squared: 0.999,
        adjusted_r_squared: 0.999,
        standard_error_of_estimate: 1064.486,
        significance_pvalue: 0.027,
    };
    const PAPER_SAMPLE_INPUTS = { Seawall_m: 1155, Precipitation_mm: 1854.4, Tropical_Storms: 1.60, Floods: 1.60 };
    const PAPER_SAMPLE_RESULT = 21806.5212;

    let lastRegression = null;
    let lastAux = null;
    let isPaperDefault = false;

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

    /** True if row has numeric Soil_loss_sqm and every predictor has a numeric value (blanks = incomplete). */
    function isRowComplete(row) {
        const target = row.Soil_loss_sqm;
        if (!target || isNaN(parseFloat(target))) return false;
        for (const col of PREDICTOR_COLUMNS) {
            const v = row[col];
            if (v === undefined || v === '' || v === null) return false;
            if (isNaN(parseFloat(v))) return false;
        }
        return true;
    }

    /** Return only rows that have valid Soil_Loss_Sqm and complete predictor data; blanks/incomplete rows are excluded. */
    function filterCompleteRows(rows) {
        return rows.filter(isRowComplete);
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
                if (col === 'Soil_loss_sqm' || col === 'Year') return;
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
        const completeRows = filterCompleteRows(rows);
        if (completeRows.length < MIN_VALID_ROWS) {
            errors.push('At least 5 rows with valid Soil_Loss_Sqm and complete predictor data are required to run regression.');
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

    function showWarnings(warnings) {
        if (!regressionWarnings) return;
        if (Array.isArray(warnings) && warnings.length > 0) {
            regressionWarnings.innerHTML = '<p class="mb-warnings-title">Note:</p><ul class="mb-warnings-list">' +
                warnings.map((w) => '<li>' + escapeHtml(String(w)) + '</li>').join('') + '</ul>';
            regressionWarnings.hidden = false;
        } else {
            regressionWarnings.innerHTML = '';
            regressionWarnings.hidden = true;
        }
    }

    function hideWarnings() {
        if (regressionWarnings) {
            regressionWarnings.innerHTML = '';
            regressionWarnings.hidden = true;
        }
    }

    function setLoading(loading) {
        btnRun.disabled = loading;
        resultsEmpty.hidden = loading || !!lastRegression;
        resultsLoading.hidden = !loading;
        resultsContent.hidden = loading || !lastRegression;
    }

    /** Return true if the given rows match the default dataset (2015–2024) so we show the paper model. */
    function isDefaultData(rows) {
        if (!Array.isArray(rows) || rows.length !== DEFAULT_INPUT_DATA.length) return false;
        for (let i = 0; i < rows.length; i++) {
            const r = rows[i];
            const d = DEFAULT_INPUT_DATA[i];
            for (const col of COLUMNS) {
                const rv = r[col];
                const dv = d[col];
                const rNum = parseFloat(rv);
                const dNum = parseFloat(dv);
                if (!Number.isNaN(rNum) && !Number.isNaN(dNum)) {
                    if (Math.abs(rNum - dNum) > 1e-6) return false;
                } else {
                    if (String(rv || '').trim() !== String(dv ?? '').trim()) return false;
                }
            }
        }
        return true;
    }

    async function runRegression() {
        const rows = collectRows();
        const errors = validateRows(rows);
        if (errors.length > 0) {
            showError(errors.join(' '));
            return;
        }
        const validRows = filterCompleteRows(rows);
        clearCellErrors();
        hideError();
        hideWarnings();

        if (isDefaultData(validRows)) {
            applyPaperDefault();
            return;
        }

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
                body: JSON.stringify({ rows: validRows }),
            });
            let data;
            try {
                data = await res.json();
            } catch (_) {
                throw new Error(res.status === 419 ? 'Session expired. Please refresh the page.' : 'Server error. Please try again.');
            }
            if (!res.ok) {
                showError(data?.message || 'Regression failed.');
                showWarnings(Array.isArray(data?.warnings) ? data.warnings : null);
                return;
            }
            if (!data.success || !data.regression) {
                showError(data?.message || 'Invalid response.');
                showWarnings(Array.isArray(data?.warnings) ? data.warnings : null);
                return;
            }
            lastRegression = data.regression;
            lastAux = data.aux ?? null;
            isPaperDefault = false;
            renderResults(data.regression);
            hideError();
            showWarnings(data.warnings);
            updateDefaultModelNote();
            updateSamplePredictionResult();
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

        /* Always persist the equation (including intercept-only) so Save Equation works after any regression run */
        renderEquation(equationStr);

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

        if (metricR2) metricR2.textContent = r2Str;
        if (metricAdjR2) metricAdjR2.textContent = adjR2Str;
        if (metricR2Secondary) metricR2Secondary.textContent = r2Str;

        const metricStdErrEstimate = document.getElementById('metricStdErrEstimate');
        const metricSignificancePvalue = document.getElementById('metricSignificancePvalue');
        if (metricStdErrEstimate) {
            const v = reg.standard_error_of_estimate;
            metricStdErrEstimate.textContent = typeof v === 'number' ? v.toLocaleString('en-US', { minimumFractionDigits: 3, maximumFractionDigits: 3 }) : '—';
        }
        if (metricSignificancePvalue) {
            const v = reg.significance_pvalue;
            metricSignificancePvalue.textContent = typeof v === 'number' ? String(v) : '—';
        }

        coefficientsTbody.innerHTML = '';
        const aux = lastAux || {};
        const pValues = reg.p_values || {};
        const stdErrors = reg.standard_errors || {};
        const tStats = reg.t_statistics || {};
        const coefs = reg.coefficients || {};
        const hasInOfficial = (name) => Object.prototype.hasOwnProperty.call(coefs, name);
        const clampP = (p) => (typeof p === 'number' && !Number.isNaN(p) ? Math.max(0, Math.min(1, p)) : null);

        function buildCoefRow(name, value, se, t, p, isIntercept) {
            const pClamped = clampP(p);
            const isSignificant = pClamped != null && pClamped < pThreshold;
            const coefCell = typeof value === 'number' ? value.toFixed(4) : '—';
            const seCell = typeof se === 'number' ? se.toFixed(4) : '—';
            const tCell = typeof t === 'number' ? t.toFixed(4) : '—';
            const pCell = pClamped != null ? pClamped.toFixed(4) : '—';
            const sigCell = isIntercept
                ? (pClamped != null && pClamped < pThreshold ? 'Yes' : '—')
                : (isSignificant ? 'Yes' : 'No');
            return `<td>${escapeHtml(name)}</td><td>${coefCell}</td><td>${seCell}</td><td>${tCell}</td><td>${pCell}</td><td>${escapeHtml(sigCell)}</td>`;
        }

        let usedAux = false;
        const interceptVal = reg.intercept ?? aux.intercept;
        const interceptSE = stdErrors.intercept ?? aux.standard_errors?.intercept;
        const interceptT = tStats.intercept ?? aux.t_statistics?.intercept;
        const interceptP = pValues.intercept ?? aux.p_values?.intercept;
        const interceptRow = document.createElement('tr');
        interceptRow.innerHTML = buildCoefRow('intercept', interceptVal, interceptSE, interceptT, interceptP, true);
        coefficientsTbody.appendChild(interceptRow);

        FULL_PREDICTOR_LIST.forEach((name) => {
            const inOfficial = hasInOfficial(name);
            const value = inOfficial ? (coefs[name] ?? aux.coefficients?.[name]) : (aux.coefficients?.[name] ?? null);
            const se = inOfficial ? (stdErrors[name] ?? aux.standard_errors?.[name]) : (aux.standard_errors?.[name] ?? null);
            const t = inOfficial ? (tStats[name] ?? aux.t_statistics?.[name]) : (aux.t_statistics?.[name] ?? null);
            const p = inOfficial ? (pValues[name] ?? aux.p_values?.[name]) : (aux.p_values?.[name] ?? null);
            if (!inOfficial && aux.coefficients && name in aux.coefficients) usedAux = true;
            const tr = document.createElement('tr');
            tr.innerHTML = buildCoefRow(name, value, se, t, p, false);
            coefficientsTbody.appendChild(tr);
        });

        const coefTableAuxNote = document.getElementById('coefTableAuxNote');
        if (coefTableAuxNote) coefTableAuxNote.hidden = !usedAux;

        resultsEmpty.hidden = true;
        resultsContent.hidden = false;
        resultsContent.querySelectorAll('.mb-fade-in').forEach((el, i) => {
            el.style.animationDelay = `${i * 0.08}s`;
        });
        updateDefaultModelNote();
        updateSamplePredictionResult();
        createMbIcons();
    }

    /** Show or hide the paper-based stats note based on isPaperDefault. */
    function updateDefaultModelNote() {
        const note = document.getElementById('paperStatsNote');
        if (note) note.hidden = !isPaperDefault;
    }

    /** Compute prediction from current model (paper or lastRegression) using sample prediction inputs. */
    function computeSamplePrediction() {
        const reg = lastRegression;
        if (!reg) return null;
        const intercept = reg.intercept;
        const coefs = reg.coefficients || {};
        const seawall = parseFloat(document.getElementById('samplePredictionSeawall')?.value, 10) || 0;
        const precip = parseFloat(document.getElementById('samplePredictionPrecip')?.value, 10) || 0;
        const tropicalStorm = parseFloat(document.getElementById('samplePredictionTropicalStorm')?.value, 10) || 0;
        const flood = parseFloat(document.getElementById('samplePredictionFlood')?.value, 10) || 0;
        let y = intercept;
        y += (coefs.Seawall_m ?? 0) * seawall;
        y += (coefs.Precipitation_mm ?? 0) * precip;
        y += (coefs.Tropical_Storms ?? 0) * tropicalStorm;
        y += (coefs.Floods ?? 0) * flood;
        return y;
    }

    /** Update the sample prediction result span from current model and input values. */
    function updateSamplePredictionResult() {
        const resultEl = document.getElementById('samplePredictionResult');
        if (!resultEl) return;
        const y = computeSamplePrediction();
        if (y === null || (typeof y === 'number' && isNaN(y))) {
            resultEl.textContent = '—';
            return;
        }
        resultEl.textContent = typeof y === 'number' ? y.toLocaleString('en-US', { minimumFractionDigits: 4, maximumFractionDigits: 4 }) + ' sq.m' : '—';
    }

    /** Apply paper-based regression as the default state: equation, coefficients, sample inputs and result. */
    function applyPaperDefault() {
        lastRegression = PAPER_REGRESSION;
        lastAux = null;
        isPaperDefault = true;
        renderResults(PAPER_REGRESSION);
        renderEquation(PAPER_EQUATION_STR);
        if (regressionEquationFormatted) regressionEquationFormatted.dataset.rawEquation = PAPER_EQUATION_STR;
        const sw = document.getElementById('samplePredictionSeawall');
        const pr = document.getElementById('samplePredictionPrecip');
        const ts = document.getElementById('samplePredictionTropicalStorm');
        const fl = document.getElementById('samplePredictionFlood');
        const res = document.getElementById('samplePredictionResult');
        if (sw) sw.value = String(PAPER_SAMPLE_INPUTS.Seawall_m);
        if (pr) pr.value = String(PAPER_SAMPLE_INPUTS.Precipitation_mm);
        if (ts) ts.value = String(PAPER_SAMPLE_INPUTS.Tropical_Storms);
        if (fl) fl.value = String(PAPER_SAMPLE_INPUTS.Floods);
        if (res) res.textContent = PAPER_SAMPLE_RESULT.toLocaleString('en-US', { minimumFractionDigits: 4, maximumFractionDigits: 4 }) + ' sq.m';
        updateDefaultModelNote();
        createMbIcons();
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

    /** Random float in [min, max], optional decimals */
    function randFloat(min, max, decimals = 0) {
        const v = min + Math.random() * (max - min);
        return decimals <= 0 ? v : Math.round(v * Math.pow(10, decimals)) / Math.pow(10, decimals);
    }

    /**
     * Validate generated example: 10 rows, unique years sorted descending (newest first), all filled, no negatives, soil loss not flat.
     */
    function validateExampleData(rows) {
        if (!Array.isArray(rows) || rows.length !== 10) return false;
        const years = rows.map((r) => Number(r.Year));
        for (let i = 1; i < years.length; i++) {
            if (years[i] >= years[i - 1] || isNaN(years[i])) return false;
        }
        const soilLosses = rows.map((r) => Number(r.Soil_loss_sqm));
        const uniqueSoil = new Set(soilLosses.map((v) => Math.round(v * 100)));
        if (uniqueSoil.size < 3) return false;
        for (const row of rows) {
            for (const col of COLUMNS) {
                const val = row[col];
                if (val === undefined || val === null || val === '') return false;
                const num = Number(val);
                if (!Number.isFinite(num) || num < 0) return false;
            }
        }
        return true;
    }

    /**
     * Generate a realistic 10-row coastal erosion sample dataset.
     * Years: consecutive startYear..startYear+9 (startYear random 2005..currentYear-9).
     * Values follow constraints and relationships (seawall/veg vs soil loss, storms/precip/floods vs soil loss).
     */
    function generateRandomDemoData() {
        const currentYear = new Date().getFullYear();
        const maxStart = Math.max(2005, currentYear - 9);
        const minStart = Math.min(2005, maxStart);

        let lastRows = null;
        for (let attempt = 0; attempt < 15; attempt++) {
            const startYear = randInt(minStart, maxStart);
            const rows = [];

            let vegBase = randInt(1900000, 2400000);

            for (let i = 0; i < 10; i++) {
                const year = startYear + i;

                let tropDep = randInt(0, 4);
                let tropStorms = randInt(0, 4);
                let sevTrop = randInt(0, 2);
                let typhoons = randInt(0, 2);
                let superTyphoons = randInt(0, 1);
                if (superTyphoons === 1 && typhoons === 0 && tropStorms < 2) {
                    typhoons = 1;
                }

                const stormLevel = tropStorms + typhoons + superTyphoons;
                let floods = randInt(0, 3);
                if (stormLevel >= 3 && Math.random() < 0.6) floods = Math.min(3, floods + randInt(1, 2));
                let stormSurges = randInt(0, 2);
                if (stormLevel >= 2 && Math.random() < 0.5) stormSurges = Math.min(2, stormSurges + 1);

                const precipBase = 1200 + randInt(0, 800) + stormLevel * randInt(80, 200);
                const precipitation_mm = Math.min(2800, Math.max(1200, precipBase + randInt(-100, 100)));

                if (floods === 0 && precipitation_mm > 2200 && Math.random() < 0.4) floods = randInt(1, 2);

                let seawall_m;
                if (i < 3) {
                    seawall_m = Math.random() < 0.65 ? 0 : randInt(0, 300);
                } else {
                    const trend = Math.min(2000, 200 + (i - 3) * randInt(180, 350) + randInt(-80, 80));
                    seawall_m = Math.max(0, trend);
                }

                vegBase = Math.max(1500000, Math.min(3000000, vegBase + randInt(-80000, 80000)));
                const vegetation_area_sqm = vegBase;

                const coastal_elevation = randFloat(1.90, 2.20, 2);

                const stormFactor = (tropStorms + typhoons * 2 + superTyphoons * 2 + floods + stormSurges) / 10;
                const precipFactor = (precipitation_mm - 1200) / 1600;
                const seawallFactor = 1 - (seawall_m / 2000) * 0.5;
                const vegFactor = 1.4 - (vegetation_area_sqm - 1500000) / 1500000 * 0.4;
                let soilLoss = 30000 + stormFactor * 50000 + precipFactor * 25000;
                soilLoss *= seawallFactor * vegFactor;
                soilLoss = Math.max(20000, Math.min(120000, soilLoss + randInt(-4000, 4000)));
                const soil_loss_sqm = Math.round(soilLoss * 100) / 100;

                rows.push({
                    Year: year,
                    Tropical_Depression: tropDep,
                    Tropical_Storms: tropStorms,
                    Severe_Tropical_Storms: sevTrop,
                    Typhoons: typhoons,
                    Super_Typhoons: superTyphoons,
                    Floods: floods,
                    Storm_Surges: stormSurges,
                    Precipitation_mm: precipitation_mm,
                    Seawall_m: seawall_m,
                    Vegetation_area_sqm: vegetation_area_sqm,
                    Coastal_Elevation: coastal_elevation,
                    Soil_loss_sqm: soil_loss_sqm,
                });
            }

            rows.sort((a, b) => Number(b.Year) - Number(a.Year));
            lastRows = rows;
            if (validateExampleData(rows)) return rows;
        }

        if (lastRows && lastRows.length === 10) {
            lastRows.sort((a, b) => Number(b.Year) - Number(a.Year));
            return lastRows;
        }
        return [];
    }

    /** Apply array of row objects to the input table; empty string for missing rows. Uses data-col so each value goes to the correct column. */
    function applyExampleToTable(example) {
        inputTable.querySelectorAll('tbody tr').forEach((tr, rowIndex) => {
            const row = example[rowIndex] || {};
            COLUMNS.forEach((col) => {
                const input = tr.querySelector(`input[data-col="${col}"]`);
                if (!input) return;
                const val = row[col];
                const str = val !== undefined && val !== null && val !== '' ? String(val) : '';
                input.value = str;
                if (str) flashInput(input);
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

    /** Load example data into the table (realistic 10-row sample; does not replace Use Default / Buguey). */
    function loadExample() {
        const data = generateRandomDemoData();
        if (data.length > 0) applyExampleToTable(data);
    }

    /** Clear the table so the user can enter new data. */
    function clearTable() {
        runNewCalculation();
    }

    function runNewCalculation() {
        inputTable.querySelectorAll('tbody input').forEach((i) => { i.value = ''; });
        clearCellErrors();
        lastRegression = null;
        lastAux = null;
        isPaperDefault = false;
        resultsEmpty.hidden = false;
        resultsContent.hidden = true;
        hideError();
        updateDefaultModelNote();
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
        } else if (val) {
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
    document.getElementById('btnClearTable')?.addEventListener('click', clearTable);

    btnCopyEquation?.addEventListener('click', () => {
        const raw = regressionEquationFormatted?.dataset?.rawEquation?.trim();
        if (!raw) return;
        navigator.clipboard?.writeText(raw).then(() => {
            const label = btnCopyEquation.querySelector('.lucide-icon')?.nextSibling;
            const orig = btnCopyEquation.innerHTML;
            btnCopyEquation.innerHTML = '<i data-lucide="check" class="lucide-icon lucide-icon-sm" aria-hidden="true"></i> Copied!';
            createMbIcons();
            setTimeout(() => {
                btnCopyEquation.innerHTML = orig;
                createMbIcons();
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
        let raw = regressionEquationFormatted?.dataset?.rawEquation?.trim();
        if (!raw && lastRegression) {
            const pThreshold = getPThreshold();
            raw = buildDynamicEquation(lastRegression, pThreshold) || '';
            if (raw && regressionEquationFormatted) regressionEquationFormatted.dataset.rawEquation = raw;
        }
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
                try {
                    sessionStorage.setItem(MB_SAVE_SUCCESS_FLAG, '1');
                } catch (_) { /* ignore */ }
                window.location.reload();
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
                showToast('Equation updated successfully.');
                const scrollY = window.scrollY;
                await loadSavedEquationsTable(currentSavedPage);
                window.scrollTo(0, scrollY);
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
                showToast('Equation deleted successfully.');
                const scrollY = window.scrollY;
                await loadSavedEquationsTable(currentSavedPage);
                window.scrollTo(0, scrollY);
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

    btnRunNew?.addEventListener('click', runNewCalculation);

    // Load default dataset (2015–2024) when user opens the Model Builder page.
    applyExampleToTable(DEFAULT_INPUT_DATA);

    // Do not show results until user clicks "Run Regression".
    lastRegression = null;
    lastAux = null;
    isPaperDefault = false;
    setLoading(false);
    updateDefaultModelNote();

    ['samplePredictionSeawall', 'samplePredictionPrecip', 'samplePredictionTropicalStorm', 'samplePredictionFlood'].forEach((id) => {
        document.getElementById(id)?.addEventListener('input', updateSamplePredictionResult);
        document.getElementById(id)?.addEventListener('change', updateSamplePredictionResult);
    });
    document.getElementById('btnResetToPaper')?.addEventListener('click', applyPaperDefault);

    createMbIcons();
});
