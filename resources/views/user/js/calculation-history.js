/**
 * TOCSEA Calculation History - View, delete, re-run, export/print (self-contained).
 * Uses shared export utilities from resources/js/shared/export-utils.js
 */

import { createIcons, History, Calculator, RotateCcw, Trash2, Inbox, Eye, Check, X, Download, ChevronDown } from 'lucide';
import {
    buildExportQuery,
    fetchExportRows,
    exportPdf,
    exportExcel,
    openPrintView,
    showToast,
} from '../../../js/shared/export-utils.js';

const page = document.getElementById('calculationHistoryPage');
if (!page) throw new Error('Calculation History page not found');

// Base URL for calculation history endpoints (delete, show, rerun)
const historyBaseUrl = page.dataset.historyBaseUrl || page.dataset.deleteUrl || '';
const deleteBaseUrl = historyBaseUrl;
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

const CH_DELETE_SUCCESS_FLAG = 'chCalculationDeletedSuccess';

if (typeof sessionStorage !== 'undefined' && sessionStorage.getItem(CH_DELETE_SUCCESS_FLAG) === '1') {
    if (typeof history !== 'undefined' && history.scrollRestoration) {
        history.scrollRestoration = 'manual';
    }
}

function showDeleteSuccessToastOnLoad() {
    try {
        if (sessionStorage.getItem(CH_DELETE_SUCCESS_FLAG) !== '1') return;
        sessionStorage.removeItem(CH_DELETE_SUCCESS_FLAG);
        if (typeof history !== 'undefined' && history.scrollRestoration) {
            history.scrollRestoration = 'manual';
        }
        window.scrollTo(0, 0);
        document.documentElement.scrollTop = 0;
        document.body.scrollTop = 0;
        requestAnimationFrame(() => showToast('Calculation deleted successfully.'));
    } catch (_) { /* ignore */ }
}

function deleteHistory(id) {
    const url = `${deleteBaseUrl}/${id}`;
    return fetch(url, {
        method: 'DELETE',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken || '',
            'Content-Type': 'application/json',
        },
    }).then((r) => {
        if (!r.ok) throw new Error(r.statusText);
        return r.json();
    });
}

const deleteModal = document.getElementById('chDeleteModal');
const deleteModalClose = document.getElementById('chDeleteModalClose');
const deleteCancel = document.getElementById('chDeleteCancel');
const deleteConfirm = document.getElementById('chDeleteConfirm');

let currentDeleteId = null;

function openDeleteModal(id) {
    currentDeleteId = id;
    if (deleteModal) {
        deleteModal.hidden = false;
        deleteModal.setAttribute('aria-hidden', 'false');
    }
}

function closeDeleteModal() {
    if (deleteModal) {
        deleteModal.hidden = true;
        deleteModal.setAttribute('aria-hidden', 'true');
    }
    currentDeleteId = null;
}

function removeRow(id) {
    const row = document.querySelector(`.ch-row[data-id="${id}"]`);
    if (row) row.remove();
    const tbody = document.querySelector('#chTable tbody');
    if (tbody && !tbody.querySelector('tr')) {
        const empty = document.getElementById('chEmpty');
        const tableWrap = document.getElementById('chTableWrap');
        if (empty) {
            empty.hidden = false;
            empty.removeAttribute('hidden');
            empty.classList.remove('hidden');
        }
        if (tableWrap) {
            tableWrap.hidden = true;
            tableWrap.classList.add('hidden');
        }
        document.querySelector('.ch-pagination')?.classList.add('hidden');
        if (page) page.dataset.hasRecords = '0';
        updateChExportButtonState();
    }
}

deleteModalClose?.addEventListener('click', closeDeleteModal);
deleteCancel?.addEventListener('click', closeDeleteModal);
deleteModal?.addEventListener('click', (e) => {
    if (e.target === deleteModal) closeDeleteModal();
});

deleteConfirm?.addEventListener('click', () => {
    const id = currentDeleteId;
    if (id == null) return;
    deleteConfirm.disabled = true;
    deleteHistory(id)
        .then(() => {
            closeDeleteModal();
            try {
                sessionStorage.setItem(CH_DELETE_SUCCESS_FLAG, '1');
            } catch (_) { /* ignore */ }
            window.location.reload();
        })
        .catch(() => {
            // deleteHistory already threw; error handling can be added here if needed
        })
        .finally(() => {
            deleteConfirm.disabled = false;
        });
});

document.querySelectorAll('.ch-btn-delete').forEach((el) => {
    el.addEventListener('click', (e) => {
        e.preventDefault();
        const id = el.getAttribute('data-id');
        if (id) openDeleteModal(id);
    });
});

// ---------------------------------------------------------------------------
// Details modal (DetailsModal component)
// ---------------------------------------------------------------------------

const detailsModal = document.getElementById('chDetailsModal');
const detailsSkeleton = document.getElementById('chDetailsSkeleton');
const detailsEmpty = document.getElementById('chDetailsEmpty');
const detailsContent = document.getElementById('chDetailsContent');
const detailsClose = document.getElementById('chDetailsClose');
const detailsCloseFooter = document.getElementById('chDetailsCloseFooter');
const detailsMeta = document.getElementById('chDetailsMeta');
const detailsMetaId = document.getElementById('chDetailsMetaId');
const detailsMetaDate = document.getElementById('chDetailsMetaDate');
const detailsUseModel = document.getElementById('chDetailsUseModel');
const detailsRecalculate = document.getElementById('chDetailsRecalculate');
const detailsDescription = document.getElementById('chDetailsDescription');

let currentDetailsId = null;
let lastFocusedElement = null;

function isModalOpen(modal) {
    return !!(modal && !modal.hidden && modal.getAttribute('aria-hidden') === 'false');
}

function trapFocusInModal(modal, event) {
    if (!isModalOpen(modal) || event.key !== 'Tab') return;

    const focusableSelectors =
        'a[href], button:not([disabled]), textarea, input, select, [tabindex]:not([tabindex="-1"])';
    const focusableElements = Array.from(modal.querySelectorAll(focusableSelectors)).filter(
        (el) => !el.hasAttribute('disabled') && el.getAttribute('aria-hidden') !== 'true'
    );

    if (focusableElements.length === 0) return;

    const first = focusableElements[0];
    const last = focusableElements[focusableElements.length - 1];

    if (event.shiftKey) {
        if (document.activeElement === first) {
            event.preventDefault();
            last.focus();
        }
    } else if (document.activeElement === last) {
        event.preventDefault();
        first.focus();
    }
}

function setDetailsState(state) {
    if (!detailsModal) return;
    if (detailsSkeleton) detailsSkeleton.classList.toggle('hidden', state !== 'loading');
    if (detailsEmpty) detailsEmpty.classList.toggle('hidden', state !== 'empty');
    if (detailsContent) detailsContent.classList.toggle('hidden', state !== 'loaded');
}

function openDetailsModal(id) {
    if (!detailsModal) return;
    currentDetailsId = id;
    lastFocusedElement =
        document.activeElement && document.activeElement instanceof HTMLElement
            ? document.activeElement
            : null;

    setDetailsState('loading');

    detailsModal.hidden = false;
    detailsModal.setAttribute('aria-hidden', 'false');

    // Ensure header meta resets quickly
    if (detailsMetaId) detailsMetaId.textContent = `#${id}`;
    if (detailsMetaDate) detailsMetaDate.textContent = 'Loading…';
    if (detailsMeta) detailsMeta.classList.remove('hidden');

    const focusTarget = detailsClose || detailsModal.querySelector('button, [href], input, select, textarea');
    if (focusTarget && focusTarget instanceof HTMLElement) {
        focusTarget.focus();
    }

    // Load details asynchronously
    void loadDetails(id);
}

function closeDetailsModal() {
    if (!detailsModal) return;
    detailsModal.hidden = true;
    detailsModal.setAttribute('aria-hidden', 'true');
    currentDetailsId = null;

    if (lastFocusedElement && typeof lastFocusedElement.focus === 'function') {
        lastFocusedElement.focus();
    }
    lastFocusedElement = null;
}

function escapeHtml(raw) {
    if (raw == null) return '';
    return String(raw)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

/** Decode HTML entities in formula text so ×, −, &nbsp; etc. display correctly in view/print/PDF. */
function decodeHtmlEntities(str) {
    if (str == null || str === '') return '';
    const div = document.createElement('div');
    div.innerHTML = String(str);
    return div.textContent || div.innerText || '';
}

function formatDateTime(isoString) {
    if (!isoString) return '—';
    try {
        const date = new Date(isoString);
        if (Number.isNaN(date.getTime())) return '—';
        return date.toLocaleString(undefined, {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
        });
    } catch {
        return '—';
    }
}

function normalizeInputs(rawInputs) {
    const result = {
        soilTypeLabel: null,
        primary: [],
        other: [],
    };

    if (!rawInputs) return result;

    let entries;
    if (Array.isArray(rawInputs)) {
        entries = rawInputs.map((value, index) => [`Input ${index + 1}`, value]);
    } else if (typeof rawInputs === 'object') {
        entries = Object.entries(rawInputs);
    } else {
        return result;
    }

    const normalizeKey = (key) => key.toLowerCase().replace(/[^a-z0-9]+/g, '_');

    const primaryKeyMap = {
        seawall: 'Seawall (m)',
        precipitation: 'Precipitation (mm)',
        tropical_storm: 'Tropical Storms',
        tropicalstorm: 'Tropical Storms',
        tropical_storms: 'Tropical Storms',
        floods: 'Floods per year',
        flood: 'Floods per year',
    };

    const formatSoilTypeLabel = (raw) => {
        if (raw == null) return null;
        const value = String(raw).trim();
        if (!value) return null;
        return value
            .replace(/_/g, ' ')
            .toLowerCase()
            .replace(/\s+/g, ' ')
            .replace(/\b\w/g, (c) => c.toUpperCase());
    };

    for (const [key, value] of entries) {
        const normKey = normalizeKey(key);

        if (normKey === 'soil_type') {
            const label = formatSoilTypeLabel(value);
            if (label) result.soilTypeLabel = label;
            continue;
        }

        const isPrimary = Object.prototype.hasOwnProperty.call(primaryKeyMap, normKey);
        const label = isPrimary
            ? primaryKeyMap[normKey]
            : key
                  .replace(/_/g, ' ')
                  .replace(/\s+/g, ' ')
                  .trim()
                  .replace(/\b\w/g, (c) => c.toUpperCase());

        const displayValue = value == null ? '—' : String(value);
        const target = isPrimary ? result.primary : result.other;

        target.push({
            label,
            value: displayValue,
        });
    }

    return result;
}

function renderDetails(data) {
    if (!detailsContent) return;

    const inputsInfo = normalizeInputs(data.inputs);
    const hasInputs = inputsInfo.primary.length > 0 || inputsInfo.other.length > 0;
    const notes = data.notes ?? '';
    const hasNotes = typeof notes === 'string' && notes.trim().length > 0;

    const resultNumber = Number.parseFloat(data.result);
    const formattedResult = Number.isFinite(resultNumber)
        ? resultNumber.toLocaleString(undefined, { maximumFractionDigits: 4 })
        : escapeHtml(data.result);

    const createdAt = formatDateTime(data.created_at);

    if (detailsDescription) {
        detailsDescription.textContent =
            createdAt && createdAt !== '—'
                ? `Calculated on ${createdAt}`
                : 'Review the inputs, outputs, and notes for this regression run.';
    }

    const hasSoilType = !!inputsInfo.soilTypeLabel;
    const formula = data.formula_snapshot ?? '';
    const formulaRaw = typeof formula === 'string' ? formula : String(formula);
    const formulaText = decodeHtmlEntities(formulaRaw);
    const isFormulaLong = formulaText.length > 220 || formulaText.split('\n').length > 4;

    // Use mb-modal-field structure (same layout as Saved Equations Edit modal)
    detailsContent.innerHTML = `
        <div class="ch-details-body">
            <div class="mb-modal-field">
                <label>Model / Equation Name</label>
                <div class="ch-details-readonly">${escapeHtml(data.equation_name ?? '—')}</div>
            </div>
            ${
                formulaText
                    ? `
            <div class="mb-modal-field">
                <label>Formula / Selected Equation</label>
                <pre class="ch-details-formula-box ${isFormulaLong ? 'is-collapsed' : ''}">${escapeHtml(formulaText)}</pre>
                ${isFormulaLong ? '<button type="button" class="ch-details-formula-toggle">Show full formula</button>' : ''}
            </div>`
                    : ''
            }
            <div class="mb-modal-field">
                <label>Inputs / Predictors</label>
                ${
                    hasInputs && (inputsInfo.primary.length > 0 || inputsInfo.other.length > 0)
                        ? `<table class="ch-details-inputs-table" role="presentation">
                <tbody>${[...inputsInfo.primary, ...inputsInfo.other]
                    .map(
                        (e) =>
                            `<tr><td class="ch-details-input-label">${escapeHtml(e.label)}</td><td class="ch-details-input-value">${escapeHtml(e.value)}</td></tr>`
                    )
                    .join('')}</tbody>
            </table>`
                        : '<p class="ch-details-empty-text">No input values recorded.</p>'
                }
            </div>
            <div class="mb-modal-field ch-details-result-field">
                <label>Result / Output</label>
                <div class="ch-details-result-value">
                    ${formattedResult} <span class="ch-result-unit">m²/year</span>
                </div>
            </div>
            <div class="mb-modal-field ch-details-meta-field">
                <label>Metadata</label>
                <div class="ch-details-readonly">
                    Date created/run: ${escapeHtml(createdAt)}
                    ${data.updated_at ? ` · Last updated: ${escapeHtml(formatDateTime(data.updated_at))}` : ''}
                </div>
            </div>
            ${
                hasNotes
                    ? `
            <div class="mb-modal-field">
                <label>Notes / Remarks</label>
                <p class="ch-details-readonly ch-details-notes">${escapeHtml(notes)}</p>
            </div>`
                    : ''
            }
        </div>
    `;

    // Wire up formula collapse/expand if present
    const formulaBox = detailsContent.querySelector('.ch-details-formula-box');
    const formulaToggle = detailsContent.querySelector('.ch-details-formula-toggle');
    if (formulaBox && formulaToggle) {
        const expandLabel = 'Show full formula';
        const collapseLabel = 'Show less';
        formulaToggle.addEventListener('click', () => {
            const isCollapsed = formulaBox.classList.toggle('is-collapsed');
            formulaToggle.textContent = isCollapsed ? expandLabel : collapseLabel;
        });
    }
}

async function loadDetails(id) {
    if (!historyBaseUrl || !detailsModal) {
        setDetailsState('empty');
        return;
    }

    setDetailsState('loading');

    const url = `${historyBaseUrl}/${encodeURIComponent(id)}`;

    try {
        const response = await fetch(url, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            throw new Error(response.statusText);
        }

        const data = await response.json();
        if (!data || typeof data !== 'object') {
            setDetailsState('empty');
            return;
        }

        renderDetails(data);

        // Toggle Use Model button based on saved_equation_id
        const hasModel = !!data.saved_equation_id;
        if (detailsUseModel) {
            detailsUseModel.classList.toggle('hidden', !hasModel);
            detailsUseModel.dataset.savedEquationId = hasModel ? String(data.saved_equation_id) : '';
        }

        setDetailsState('loaded');
    } catch {
        setDetailsState('empty');
    }
}

// Wire up DetailsModal triggers
document.querySelectorAll('.ch-btn-view').forEach((el) => {
    el.addEventListener('click', (e) => {
        e.preventDefault();
        const id = el.getAttribute('data-id');
        if (id) openDetailsModal(id);
    });
});

// Close actions
detailsClose?.addEventListener('click', () => {
    closeDetailsModal();
});

detailsCloseFooter?.addEventListener('click', () => {
    closeDetailsModal();
});

detailsModal?.addEventListener('click', (event) => {
    if (event.target === detailsModal) {
        closeDetailsModal();
    }
});

// Footer action buttons
detailsRecalculate?.addEventListener('click', () => {
    if (!currentDetailsId || !historyBaseUrl) return;
    const url = `${historyBaseUrl}/${encodeURIComponent(currentDetailsId)}/rerun`;
    window.location.href = url;
});

detailsUseModel?.addEventListener('click', () => {
    const savedEquationId = detailsUseModel.dataset.savedEquationId;
    if (!savedEquationId) return;
    // Navigate to Model Builder saved equations, filtered/highlighted if needed.
    // Keeps behavior simple and predictable for now.
    window.location.href = '/model-builder/saved-equations';
});

// Global keyboard handling: Escape + focus trap
document.addEventListener('keydown', (e) => {
    if (e.key === 'Tab') {
        if (isModalOpen(detailsModal)) {
            trapFocusInModal(detailsModal, e);
            return;
        }
        if (isModalOpen(deleteModal)) {
            trapFocusInModal(deleteModal, e);
        }
        return;
    }

    if (e.key !== 'Escape') return;

    const detailsOpen = isModalOpen(detailsModal);
    const deleteOpen = isModalOpen(deleteModal);

    if (detailsOpen) {
        e.preventDefault();
        closeDetailsModal();
    } else if (deleteOpen) {
        e.preventDefault();
        closeDeleteModal();
    }
});

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', showDeleteSuccessToastOnLoad);
} else {
    showDeleteSuccessToastOnLoad();
}

/* Clear filters: redirect to index without query params */
const chFiltersForm = document.getElementById('chFiltersForm');
const chClearFiltersBtn = document.getElementById('chClearFilters');
chClearFiltersBtn?.addEventListener('click', () => {
    if (chFiltersForm?.action) {
        window.location.href = chFiltersForm.action;
    }
});

createIcons({ icons: { History, Calculator, RotateCcw, Trash2, Inbox, Eye, Check, X, Download, ChevronDown }, nameAttr: 'data-lucide' });

// ---------------------------------------------------------------------------
// Export / Print (self-contained; same UI as Saved Equations, data from export API)
// ---------------------------------------------------------------------------

const exportUrl = page?.dataset?.exportUrl || (historyBaseUrl ? historyBaseUrl + '/export' : '');
const chExportBtn = document.getElementById('chExportBtn');
const chExportMenu = document.getElementById('chExportMenu');
const chExportWrap = document.querySelector('.mb-se-export-wrap');
const chTableWrap = document.getElementById('chTableWrap');
const chTable = document.getElementById('chTable');

let chExportInProgress = false;

function setChExportLoading(loading) {
    chExportInProgress = loading;
    if (chExportBtn) chExportBtn.disabled = loading;
    if (chExportMenu) {
        chExportMenu.querySelectorAll('.mb-se-export-item').forEach((item) => { item.disabled = loading; });
    }
}

function updateChExportButtonState() {
    if (!chExportBtn || chExportInProgress) return;
    const hasRecords = page?.dataset?.hasRecords === '1' || (chTableWrap && !chTableWrap.hidden && chTable?.querySelectorAll('.ch-row').length > 0);
    chExportBtn.disabled = !hasRecords;
}

const CH_EXPORT_COLUMNS = [
    { key: 'date_time', label: 'Date / Time' },
    { key: 'equation_name', label: 'Equation Name' },
    { key: 'inputs', label: 'Inputs' },
    { key: 'result_formatted', label: 'Result' },
];

function getChExportParams(scope) {
    const params = new URLSearchParams(window.location?.search || '');
    const obj = {};
    ['q', 'from', 'to', 'equation', 'sort'].forEach((k) => {
        const v = params.get(k);
        if (v != null && v !== '') obj[k] = v;
    });
    if (scope === 'page') {
        obj.page = Math.max(1, parseInt(params.get('page'), 10) || 1);
        obj.per_page = parseInt(page?.dataset?.perPage, 10) || 10;
    }
    return obj;
}

/** Export scope: match Saved Equation — export current view only (current page). */
const CH_EXPORT_SCOPE = 'page';

/** Base URL for server-rendered PDF (same filters as JSON export). */
function getChPdfExportUrl() {
    const base = (exportUrl || '').replace(/\/export\/?$/, '');
    return base ? base + '/export/pdf' : '';
}

async function chRunExport(format) {
    if (format === 'pdf') {
        const pdfUrl = getChPdfExportUrl();
        if (!pdfUrl) { showToast('Export URL not configured.'); return; }
        const params = getChExportParams(CH_EXPORT_SCOPE);
        params.scope = CH_EXPORT_SCOPE;
        const qs = new URLSearchParams(params).toString();
        const url = qs ? pdfUrl + '?' + qs : pdfUrl;
        setChExportLoading(true);
        try {
            const res = await fetch(url, { method: 'GET', credentials: 'same-origin' });
            if (!res.ok) throw new Error(res.statusText);
            const blob = await res.blob();
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = 'Calculation_History_Report.pdf';
            a.click();
            URL.revokeObjectURL(a.href);
        } catch (e) {
            console.error('PDF export failed:', e);
            showToast('PDF export failed. Please try again.');
        } finally {
            setChExportLoading(false);
            updateChExportButtonState();
        }
        return;
    }

    const params = getChExportParams(CH_EXPORT_SCOPE);
    const url = buildExportQuery(exportUrl, params, CH_EXPORT_SCOPE);
    if (!url) { showToast('Export URL not configured.'); return; }
    setChExportLoading(true);
    try {
        const rows = await fetchExportRows(url);
        if (rows.length === 0) return;
        const getCellValues = (r) => [r.date_time, r.equation_name, r.inputs, r.result_formatted];
        const datePart = new Date().toISOString().slice(0, 10);
        const opts = {
            reportTitle: 'Calculation History Report',
            columns: CH_EXPORT_COLUMNS,
            getCellValues,
        };
        if (format === 'excel') {
            const ok = await exportExcel(rows, {
                columns: CH_EXPORT_COLUMNS,
                getCellValues: (r) => [r.date_time, r.equation_name, r.inputs, r.result],
                sheetName: 'Calculation History',
                filename: 'tocsea-calculation-history-' + datePart + '.xlsx',
            });
            if (!ok) showToast('Export failed. Please try again.');
        } else if (format === 'print') {
            const ok = openPrintView(rows, opts);
            if (!ok) showToast('Print failed. Please try again.');
        }
    } catch (e) {
        console.error('Export failed:', e);
        showToast('Export failed. Please try again.');
    } finally {
        setChExportLoading(false);
        updateChExportButtonState();
    }
}

if (chExportBtn && chExportMenu) {
    chExportBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        if (chExportBtn.disabled) return;
        const wasOpen = !chExportMenu.hidden;
        chExportMenu.hidden = wasOpen;
        chExportWrap?.classList.toggle('is-open', !wasOpen);
        chExportBtn.setAttribute('aria-expanded', String(!wasOpen));
    });
    chExportMenu.querySelectorAll('.mb-se-export-item').forEach((item) => {
        item.addEventListener('click', () => {
            const format = item.getAttribute('data-export');
            chExportMenu.hidden = true;
            chExportWrap?.classList.remove('is-open');
            chExportBtn?.setAttribute('aria-expanded', 'false');
            chRunExport(format);
        });
    });
}
document.addEventListener('click', (e) => {
    if (chExportMenu && !chExportMenu.hidden && !e.target.closest('.mb-se-export-wrap')) {
        chExportMenu.hidden = true;
        chExportWrap?.classList.remove('is-open');
        if (chExportBtn) chExportBtn.setAttribute('aria-expanded', 'false');
    }
});

updateChExportButtonState();
