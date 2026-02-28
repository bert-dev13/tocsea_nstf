/**
 * TOCSEA Calculation History - View, delete, re-run, export/print (self-contained).
 */

import { createIcons, History, Calculator, RotateCcw, Trash2, Inbox, Eye, Check, X, Download, ChevronDown } from 'lucide';
import { jsPDF } from 'jspdf';
import html2canvas from 'html2canvas';

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

const CHECK_CIRCLE_SVG = '<svg class="mb-save-toast-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>';
const TOAST_DURATION_MS = 2500;
let chToastDismissTimeout = null;

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
    if (chToastDismissTimeout) {
        clearTimeout(chToastDismissTimeout);
        chToastDismissTimeout = null;
    }
    const safe = String(message).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    toast.innerHTML = CHECK_CIRCLE_SVG + '<span>' + safe + '</span>';
    toast.classList.remove('mb-save-toast-visible');
    requestAnimationFrame(() => {
        requestAnimationFrame(() => {
            toast.classList.add('mb-save-toast-visible');
        });
    });
    chToastDismissTimeout = setTimeout(() => {
        toast.classList.remove('mb-save-toast-visible');
        chToastDismissTimeout = null;
    }, TOAST_DURATION_MS);
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
    const formulaText = typeof formula === 'string' ? formula : String(formula);
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
const chExportWrap = document.querySelector('.ch-export-wrap');
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

/** Build export URL with current filter query string (same as index). */
function getChExportApiUrl() {
    if (!exportUrl) return '';
    const qs = typeof window !== 'undefined' && window.location?.search ? window.location.search : '';
    return exportUrl + (qs ? (exportUrl.includes('?') ? '&' + qs.slice(1) : qs) : '');
}

/** Fetch all filtered rows from backend (respects search, filters, sort). */
function fetchChExportRows() {
    const url = getChExportApiUrl();
    if (!url) return Promise.resolve([]);
    return fetch(url, {
        method: 'GET',
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    })
        .then((r) => { if (!r.ok) throw new Error(r.statusText); return r.json(); })
        .then((data) => data?.rows || [])
        .catch(() => []);
}

function getChExportDateString() {
    return new Date().toLocaleString(undefined, {
        year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit',
    });
}

function escapeHtmlExport(s) {
    if (s == null) return '';
    return String(s)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

/** Inline styles for print iframe (report only, no nav/sidebar); repeat header on each page. */
const CH_PRINT_STYLES =
    'body{margin:0;padding:14mm;font-family:system-ui,sans-serif;font-size:12px;line-height:1.5;color:#111;}' +
    '.ch-report-title{margin:0 0 4px;font-size:18px;font-weight:700;}' +
    '.ch-report-subtitle{margin-bottom:12px;font-size:11px;color:#444;}' +
    '.ch-report-table{width:100%;border-collapse:collapse;margin-top:8px;table-layout:fixed;}' +
    '.ch-report-table th,.ch-report-table td{padding:8px;text-align:left;border:1px solid #bbb;vertical-align:top;white-space:normal;overflow-wrap:anywhere;word-break:break-word;}' +
    '.ch-report-table th{background:#424242;color:#fff;font-weight:700;}' +
    '.ch-report-table thead{display:table-header-group;}';

/** Build report table HTML for print/PDF (no Actions column). */
function buildChReportTableHtml(rows, dateStr) {
    const thead = '<thead><tr><th>Date / Time</th><th>Equation Name</th><th>Inputs</th><th>Result</th></tr></thead>';
    const tbodyRows = rows
        .map((r) =>
            '<tr><td>' + escapeHtmlExport(r.date_time) + '</td><td>' + escapeHtmlExport(r.equation_name) + '</td><td>' +
            escapeHtmlExport(r.inputs) + '</td><td>' + escapeHtmlExport(r.result_formatted) + '</td></tr>'
        )
        .join('');
    return (
        '<h1 class="ch-report-title">Calculation History Report</h1>' +
        '<p class="ch-report-subtitle">TOCSEA &middot; ' + escapeHtmlExport(dateStr) + '</p>' +
        '<table class="ch-report-table">' + thead + '<tbody>' + tbodyRows + '</tbody></table>'
    );
}

/** PDF: A4 portrait (or landscape if many columns); repeat header, wrap text. */
async function chExportPdf() {
    const rows = await fetchChExportRows();
    if (rows.length === 0) {
        showToast('No data to export.');
        return;
    }
    setChExportLoading(true);
    const dateStr = getChExportDateString();
    const wrap = document.createElement('div');
    wrap.className = 'ch-pdf-export-wrap';
    wrap.innerHTML = buildChReportTableHtml(rows, dateStr);
    const a4WidthPx = 794;
    wrap.style.width = a4WidthPx + 'px';
    wrap.style.padding = '14mm';
    wrap.style.fontFamily = 'system-ui, sans-serif';
    wrap.style.fontSize = '12px';
    document.body.appendChild(wrap);
    try {
        if (typeof document.fonts !== 'undefined' && document.fonts.ready) await document.fonts.ready;
        const scale = 2;
        const canvas = await html2canvas(wrap, { scale, useCORS: true, logging: false, backgroundColor: '#ffffff' });
        wrap.remove();
        const imgW = (canvas.width / scale) * 0.264583333;
        const imgH = (canvas.height / scale) * 0.264583333;
        const colCount = 4;
        const doc = new jsPDF({ orientation: colCount > 5 ? 'l' : 'p', unit: 'mm', format: 'a4' });
        const pageW = doc.internal.pageSize.getWidth();
        const pageH = doc.internal.pageSize.getHeight();
        let w = imgW;
        let h = imgH;
        if (h > pageH) { const ratio = pageH / h; w *= ratio; h = pageH; }
        if (w > pageW) { const ratio = pageW / w; w = pageW; h *= ratio; }
        doc.addImage(canvas.toDataURL('image/png'), 'PNG', 0, 0, w, h);
        doc.save('Calculation_History_Report.pdf');
    } catch (e) {
        console.error('PDF export failed:', e);
        if (wrap.parentNode) wrap.remove();
        showToast('PDF export failed. Please try again.');
    } finally {
        setChExportLoading(false);
        updateChExportButtonState();
    }
}

/** Excel: styled header, borders, auto width, dates/numbers; filename calculation-history_YYYY-MM-DD.xlsx */
async function chExportExcel() {
    const rows = await fetchChExportRows();
    if (rows.length === 0) {
        showToast('No data to export.');
        return;
    }
    setChExportLoading(true);
    try {
        const XLSX = await import('xlsx');
        const headers = ['Date / Time', 'Equation Name', 'Inputs', 'Result (m²/year)'];
        const data = [
            headers,
            ...rows.map((r) => [r.date_time, r.equation_name, r.inputs, r.result]),
        ];
        const ws = XLSX.utils.aoa_to_sheet(data);
        ws['!cols'] = [{ wch: 18 }, { wch: 28 }, { wch: 14 }, { wch: 16 }];
        const range = XLSX.utils.decode_range(ws['!ref'] || 'A1');
        for (let c = range.s.c; c <= range.e.c; c++) {
            const addr = XLSX.utils.encode_cell({ r: 0, c });
            if (ws[addr]) ws[addr].s = { font: { bold: true }, fill: { fgColor: { rgb: 'FFE0E0E0' } } };
        }
        for (let r = 1; r <= rows.length; r++) {
            const addr = XLSX.utils.encode_cell({ r, c: 3 });
            if (ws[addr] && typeof rows[r - 1].result === 'number') ws[addr].z = '#,##0.00';
        }
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, 'Calculation History');
        const datePart = new Date().toISOString().slice(0, 10);
        XLSX.writeFile(wb, 'calculation-history_' + datePart + '.xlsx');
    } catch (e) {
        console.error('Excel export failed:', e);
        showToast('Export failed. Please try again.');
    } finally {
        setChExportLoading(false);
        updateChExportButtonState();
    }
}

/** Print: iframe with report only; repeat header, no sidebar/nav. */
function chPrint() {
    setChExportLoading(true);
    fetchChExportRows()
        .then((rows) => {
            if (rows.length === 0) {
                showToast('No data to print.');
                return;
            }
            const dateStr = getChExportDateString();
            const html =
                '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Calculation History Report - TOCSEA</title>' +
                '<style>' + CH_PRINT_STYLES + '</style></head><body>' +
                buildChReportTableHtml(rows, dateStr) +
                '</body></html>';
            const iframe = document.createElement('iframe');
            iframe.setAttribute('style', 'position:absolute;width:0;height:0;border:none;');
            document.body.appendChild(iframe);
            const doc = iframe.contentWindow?.document;
            if (!doc) { iframe.remove(); showToast('Print failed. Please try again.'); return; }
            doc.open();
            doc.write(html);
            doc.close();
            const win = iframe.contentWindow;
            if (win) {
                win.focus();
                requestAnimationFrame(() => {
                    setTimeout(() => {
                        win.print();
                        win.addEventListener('afterprint', () => { iframe.remove(); }, { once: true });
                    }, 150);
                });
            } else iframe.remove();
        })
        .catch(() => showToast('Print failed. Please try again.'))
        .finally(() => {
            setChExportLoading(false);
            updateChExportButtonState();
        });
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
            const action = item.getAttribute('data-export');
            chExportMenu.hidden = true;
            chExportWrap?.classList.remove('is-open');
            chExportBtn?.setAttribute('aria-expanded', 'false');
            if (action === 'pdf') chExportPdf();
            else if (action === 'excel') chExportExcel();
            else if (action === 'print') chPrint();
        });
    });
}
document.addEventListener('click', (e) => {
    if (chExportMenu && !chExportMenu.hidden && !e.target.closest('.ch-export-wrap')) {
        chExportMenu.hidden = true;
        chExportWrap?.classList.remove('is-open');
        if (chExportBtn) chExportBtn.setAttribute('aria-expanded', 'false');
    }
});

updateChExportButtonState();
