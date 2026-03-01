/**
 * TOCSEA Admin Model Management - View modal, actions, Paginated List, Export (PDF, Excel, Print)
 * Formula column: full text, no truncation; PDF/Print match web UI; UTF-8 (×, −, +) preserved.
 */

import { usePagination, renderAdminPagination } from './pagination.js';
import { jsPDF } from 'jspdf';
import html2canvas from 'html2canvas';
import {
    createIcons,
    Layers,
    Search,
    Eye,
    X,
    Loader2,
    Trash2,
    AlertTriangle,
    ChevronDown,
    Download,
    FileSpreadsheet,
    FileText,
    Printer,
} from 'lucide';

const ADMIN_MODELS_ICONS = {
    Layers,
    Search,
    Eye,
    X,
    Loader2,
    Trash2,
    AlertTriangle,
    ChevronDown,
    Download,
    FileSpreadsheet,
    FileText,
    Printer,
};

const API_MODELS_URL = '/api/admin/models';

function getConfig() {
    return window.ADMIN_MODELS_CONFIG || {};
}

function getQueryParams() {
    const params = new URLSearchParams(window.location.search);
    return {
        page: Math.max(1, parseInt(params.get('page'), 10) || 1),
        pageSize: Math.min(100, Math.max(1, parseInt(params.get('pageSize'), 10) || 25)),
        search: params.get('search') || '',
        createdBy: params.get('createdBy') || '',
        from: params.get('from') || params.get('dateFrom') || '',
        to: params.get('to') || params.get('dateTo') || '',
        sort: params.get('sort') || 'newest',
    };
}

function buildModelsApiUrl(params) {
    const p = new URLSearchParams();
    if (params.page) p.set('page', params.page);
    if (params.pageSize) p.set('pageSize', params.pageSize);
    if (params.search) p.set('search', params.search);
    if (params.createdBy) p.set('createdBy', params.createdBy);
    if (params.from) p.set('from', params.from);
    if (params.to) p.set('to', params.to);
    if (params.sort) p.set('sort', params.sort);
    return API_MODELS_URL + (p.toString() ? '?' + p.toString() : '');
}

function updateUrl(params) {
    const url = new URL(window.location);
    Object.entries(params).forEach(([k, v]) => {
        if (v === '' || v == null) url.searchParams.delete(k);
        else url.searchParams.set(k, String(v));
    });
    window.history.replaceState({}, '', url.toString());
}

function renderModelRow(model) {
    const formulaRaw = model.formula || '';
    const formulaDecoded = decodeHtmlEntities(formulaRaw);
    const formulaDisplay = formulaDecoded.trim() || '—';
    return `
        <tr data-model-id="${model.id}">
            <td><span class="admin-models-cell-name">${escapeHtml(model.equation_name)}</span></td>
            <td class="admin-models-col-formula"><span class="admin-models-cell-formula">${escapeHtml(formulaDisplay)}</span></td>
            <td>${escapeHtml(model.created_at)}</td>
            <td class="admin-models-col-actions no-print">
                <div class="admin-models-actions">
                    <button type="button" class="admin-models-action-btn admin-models-action-view" data-model-id="${model.id}" title="View formula" aria-label="View ${escapeHtml(model.equation_name)}">
                        <i data-lucide="eye" class="lucide-icon lucide-icon-sm"></i>
                    </button>
                    <button type="button" class="admin-models-action-btn admin-models-action-delete" data-model-id="${model.id}" data-model-name="${escapeHtml(model.equation_name)}" title="Delete" aria-label="Delete ${escapeHtml(model.equation_name)}">
                        <i data-lucide="trash-2" class="lucide-icon lucide-icon-sm"></i>
                    </button>
                </div>
            </td>
        </tr>
    `;
}

async function fetchModels() {
    const params = getQueryParams();
    const skeleton = document.getElementById('adminModelsTableSkeleton');
    const emptyEl = document.getElementById('adminModelsEmpty');
    const table = document.getElementById('adminModelsTable');
    const tbody = document.getElementById('adminModelsTableBody');
    const paginationContainer = document.getElementById('adminModelsPagination');

    skeleton.style.display = '';
    emptyEl.style.display = 'none';
    table.style.display = 'none';
    if (tbody) tbody.innerHTML = '';
    paginationContainer.innerHTML = '';

    try {
        const url = buildModelsApiUrl(params);
        const res = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        if (!res.ok) throw new Error('Failed to load models');
        const json = await res.json();
        skeleton.style.display = 'none';

        const { data, pagination } = json;
        const meta = usePagination({
            page: pagination.page,
            pageSize: pagination.pageSize,
            totalItems: pagination.totalItems,
        });

        if (!data || data.length === 0) {
            emptyEl.style.display = '';
            createIcons({ icons: ADMIN_MODELS_ICONS, attrs: { 'stroke-width': 2 } });
        } else {
            table.style.display = '';
            tbody.innerHTML = data.map(renderModelRow).join('');
            bindRowActions(tbody);
            createIcons({ icons: ADMIN_MODELS_ICONS, attrs: { 'stroke-width': 2 } });
        }

        renderAdminPagination(paginationContainer, meta, {
            loading: false,
            containerId: 'admin-models-pagination',
            onPageChange: (page) => {
                updateUrl({ ...params, page });
                fetchModels();
            },
            onPageSizeChange: (pageSize) => {
                updateUrl({ ...params, page: 1, pageSize });
                fetchModels();
            },
        });
    } catch (err) {
        skeleton.style.display = 'none';
        emptyEl.style.display = '';
        const p = emptyEl.querySelector('p');
        if (p) p.textContent = 'Failed to load models. Please try again.';
        const hint = emptyEl.querySelector('.admin-users-empty-hint');
        if (hint) hint.textContent = 'Check your connection and refresh.';
        createIcons({ icons: ADMIN_MODELS_ICONS, attrs: { 'stroke-width': 2 } });
    }
}

function getCsrfHeaders() {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || getConfig().csrfToken;
    return {
        'X-CSRF-TOKEN': token,
        'Accept': 'application/json',
        'Content-Type': 'application/json',
    };
}

function routeUrl(key, id) {
    const r = getConfig().routes?.[key];
    return r ? r.replace('__ID__', String(id)) : null;
}

function escapeHtml(str) {
    if (str == null) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

/**
 * Decode HTML entities (e.g. &times;, &minus;, &nbsp;) to plain Unicode so formula
 * displays correctly in table, modal, PDF, and print. Use before escapeHtml for display.
 */
function decodeHtmlEntities(str) {
    if (str == null || str === '') return '';
    const div = document.createElement('div');
    div.innerHTML = String(str);
    return div.textContent || div.innerText || '';
}

function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (!modal) return;
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
    createIcons({ icons: ADMIN_MODELS_ICONS, attrs: { 'stroke-width': 2 } });
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (!modal) return;
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
}

function bindModalClose(modalId) {
    const modal = document.getElementById(modalId);
    if (!modal) return;
    modal.querySelectorAll('[data-close-modal]').forEach(btn => {
        btn.addEventListener('click', () => closeModal(modalId));
    });
    modal.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeModal(modalId);
    });
}

let alertAutoHideTimer = null;

function showAlert(message, isError = false) {
    const alertEl = document.getElementById('adminModelsAlert');
    const errorEl = document.getElementById('adminModelsErrorAlert');
    const alertText = document.getElementById('adminModelsAlertText');
    const errorText = document.getElementById('adminModelsErrorAlertText');
    if (!alertEl || !errorEl) return;
    if (alertAutoHideTimer) {
        clearTimeout(alertAutoHideTimer);
        alertAutoHideTimer = null;
    }
    alertEl.style.display = 'none';
    errorEl.style.display = 'none';
    if (isError) {
        if (errorText) errorText.textContent = message;
        errorEl.style.display = 'flex';
    } else {
        if (alertText) alertText.textContent = message;
        alertEl.style.display = 'flex';
        alertEl.classList.add('admin-alert-popup-visible');
        alertAutoHideTimer = setTimeout(() => {
            alertEl.classList.remove('admin-alert-popup-visible');
            alertAutoHideTimer = null;
        }, 4000);
    }
    createIcons({ icons: ADMIN_MODELS_ICONS, attrs: { 'stroke-width': 2 } });
}

function renderViewModelContent(data) {
    const formulaRaw = data.formula || '';
    const formulaDecoded = decodeHtmlEntities(formulaRaw);
    const formula = formulaRaw ? escapeHtml(formulaDecoded.trim() || '—') : '—';
    const hasCreatedBy = data.created_by && String(data.created_by).trim() !== '' && data.created_by !== '—';
    const hasPredictors = Array.isArray(data.predictors) && data.predictors.length > 0;
    const predictorsHtml = hasPredictors
        ? data.predictors.map(p => `<li>${escapeHtml(p)}</li>`).join('')
        : '';
    const hasLocation = data.location && String(data.location).trim() !== '' && data.location !== '—';
    const hasNotes = data.notes && String(data.notes).trim() !== '' && data.notes !== '—';

    const parts = [];
    if (hasCreatedBy) {
        parts.push(`
        <div class="admin-view-model-field">
            <span class="admin-view-model-label">Created by</span>
            <span class="admin-view-model-value">${escapeHtml(data.created_by)}</span>
        </div>`);
    }
    parts.push(`
        <div class="admin-view-model-field">
            <span class="admin-view-model-label">Date created</span>
            <span class="admin-view-model-value">${escapeHtml(data.created_at || '—')}</span>
        </div>
        <div class="admin-view-model-field">
            <span class="admin-view-model-label">Formula</span>
            <div class="admin-view-model-formula">${formula}</div>
        </div>`);
    if (hasPredictors) {
        parts.push(`
        <div class="admin-view-model-field">
            <span class="admin-view-model-label">Predictors</span>
            <ul class="admin-view-model-predictors">${predictorsHtml}</ul>
        </div>`);
    }
    if (hasLocation) {
        parts.push(`
        <div class="admin-view-model-field">
            <span class="admin-view-model-label">Location</span>
            <span class="admin-view-model-value">${escapeHtml(data.location)}</span>
        </div>`);
    }
    if (hasNotes) {
        parts.push(`
        <div class="admin-view-model-field">
            <span class="admin-view-model-label">Notes</span>
            <span class="admin-view-model-value">${escapeHtml(data.notes)}</span>
        </div>`);
    }
    return parts.join('');
}

async function handleView(e) {
    const modelId = e.currentTarget.dataset.modelId;
    if (!modelId) return;
    const contentEl = document.getElementById('adminViewModelContent');
    if (!contentEl) return;
    const showUrl = routeUrl('show', modelId);
    if (!showUrl) return;
    openModal('adminViewModelModal');
    contentEl.innerHTML = `
        <div class="admin-modal-loading">
            <i data-lucide="loader-2" class="lucide-icon lucide-icon-lg admin-spin" aria-hidden="true"></i>
            <span>Loading…</span>
        </div>
    `;
    createIcons({ icons: ADMIN_MODELS_ICONS, attrs: { 'stroke-width': 2 } });
    try {
        const res = await fetch(showUrl, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        const data = await res.json();
        if (res.ok) {
            const subEl = document.getElementById('adminViewModelSubtitle');
            if (subEl) subEl.textContent = data.name ? `Viewing: ${data.name}` : '';
            contentEl.innerHTML = renderViewModelContent(data);
        } else {
            contentEl.innerHTML = '<p class="admin-modal-error">Failed to load model details.</p>';
        }
    } catch (err) {
        contentEl.innerHTML = '<p class="admin-modal-error">Failed to load model details. Please try again.</p>';
    }
    createIcons({ icons: ADMIN_MODELS_ICONS, attrs: { 'stroke-width': 2 } });
}

function handleDelete(e) {
    const btn = e.currentTarget;
    document.getElementById('adminDeleteModelName').textContent = btn.dataset.modelName || 'This model';
    document.getElementById('adminDeleteModelConfirmBtn').dataset.modelId = btn.dataset.modelId;
    openModal('adminDeleteModelModal');
}

async function deleteModel() {
    const modelId = document.getElementById('adminDeleteModelConfirmBtn')?.dataset.modelId;
    if (!modelId) return;
    const url = routeUrl('destroy', modelId);
    if (!url) return;
    const btn = document.getElementById('adminDeleteModelConfirmBtn');
    btn.disabled = true;
    btn.innerHTML = '<i data-lucide="loader-2" class="lucide-icon lucide-icon-sm admin-spin"></i> Deleting…';
    createIcons({ icons: ADMIN_MODELS_ICONS, attrs: { 'stroke-width': 2 } });
    try {
        const res = await fetch(url, {
            method: 'DELETE',
            headers: getCsrfHeaders(),
        });
        const data = await res.json().catch(() => ({}));
        if (res.ok && data.success) {
            closeModal('adminDeleteModelModal');
            fetchModels();
            showAlert(data.message || 'Model deleted.');
        } else {
            showAlert(data.message || 'Failed to delete model.', true);
        }
    } catch (err) {
        showAlert('An error occurred. Please try again.', true);
    }
    btn.disabled = false;
    btn.textContent = 'Confirm Delete';
}

function bindRowActions(container) {
    const scope = container || document;
    scope.querySelectorAll('.admin-models-action-view').forEach(btn => {
        btn.addEventListener('click', handleView);
    });
    scope.querySelectorAll('.admin-models-action-delete').forEach(btn => {
        btn.addEventListener('click', handleDelete);
    });
}

async function fetchAllModelsForExport() {
    const params = { ...getQueryParams(), page: 1, pageSize: 100 };
    const all = [];
    let hasMore = true;
    while (hasMore) {
        const url = buildModelsApiUrl(params);
        const res = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        if (!res.ok) throw new Error('Failed to load models');
        const json = await res.json();
        const { data, pagination } = json;
        all.push(...(data || []));
        const total = pagination?.totalItems ?? 0;
        const fetched = all.length;
        hasMore = fetched < total;
        if (hasMore) params.page += 1;
    }
    return all;
}

/**
 * Landscape A4 dimensions in px at 96dpi for html2canvas capture.
 * 297mm ≈ 1122px, 210mm ≈ 794px.
 */
const PDF_PAGE_WIDTH_PX = 1122;
const PDF_PAGE_HEIGHT_PX = 794;

/**
 * Column width proportions: Equation Name ~25%, Formula ~55–60%, Date Created ~15–20%.
 * Table uses table-layout:fixed with explicit col widths for full-width professional layout.
 */
const PDF_EXPORT_TABLE_STYLES =
    '.admin-models-pdf-report{font-family:system-ui,sans-serif;font-size:12px;color:#111;box-sizing:border-box;}' +
    '.admin-models-pdf-report table{width:100%;border-collapse:collapse;table-layout:fixed;}' +
    '.admin-models-pdf-report th,.admin-models-pdf-report td{padding:10px 12px;text-align:left;border:1px solid #d1d5db;vertical-align:top;box-sizing:border-box;}' +
    '.admin-models-pdf-report th{background:#424242;color:#fff;font-weight:600;}' +
    '.admin-models-pdf-report tbody tr{border-bottom:1px solid #e5e7eb;}' +
    '.admin-models-pdf-report tbody tr:nth-child(even){background:#f9fafb;}' +
    '.admin-models-pdf-report .admin-models-pdf-col-name{width:25%;}' +
    '.admin-models-pdf-report .admin-models-pdf-col-formula{width:58%;letter-spacing:normal;word-spacing:normal;white-space:pre-wrap;word-break:break-word;overflow-wrap:break-word;overflow:visible;}' +
    '.admin-models-pdf-report .admin-models-pdf-col-date{width:17%;}' +
    '.admin-models-pdf-report .admin-models-pdf-cell-formula{font-family:ui-monospace,Consolas,Monaco,monospace;font-size:11px;line-height:1.5;letter-spacing:normal;word-spacing:normal;white-space:pre-wrap;word-break:break-word;overflow-wrap:break-word;}';

/**
 * Build report table DOM for PDF – full formulas, no truncation, UTF-8 (×, −, +) safe.
 * Colgroup enforces column proportions: 25% name, 58% formula, 17% date.
 */
function createModelsReportLayout(rows, dateStr) {
    const wrap = document.createElement('div');
    wrap.className = 'admin-models-pdf-report';
    const tableRows = rows
        .map((r) => {
            const formulaRaw = r.formula || '';
            const formulaDecoded = decodeHtmlEntities(formulaRaw);
            const formulaDisplay = formulaDecoded.trim() ? escapeHtml(formulaDecoded.trim()) : '—';
            return (
                '<tr>' +
                '<td class="admin-models-pdf-col-name">' + escapeHtml(r.equation_name || '—') + '</td>' +
                '<td class="admin-models-pdf-col-formula"><span class="admin-models-pdf-cell-formula">' + formulaDisplay + '</span></td>' +
                '<td class="admin-models-pdf-col-date">' + escapeHtml(r.created_at || '—') + '</td>' +
                '</tr>'
            );
        })
        .join('');
    wrap.innerHTML =
        '<h2 class="admin-models-pdf-title">TOCSEA – Model Management Report</h2>' +
        '<p class="admin-models-pdf-date">Generated: ' + escapeHtml(dateStr) + '</p>' +
        '<table>' +
        '<colgroup><col class="admin-models-pdf-col-name"><col class="admin-models-pdf-col-formula"><col class="admin-models-pdf-col-date"></colgroup>' +
        '<thead><tr><th>Equation Name</th><th>Formula</th><th>Date Created</th></tr></thead>' +
        '<tbody>' + tableRows + '</tbody></table>';
    return wrap;
}

async function exportModelsToPdf() {
    try {
        const rows = await fetchAllModelsForExport();
        if (rows.length === 0) {
            showAlert('No models to export.', true);
            return;
        }

        const dateStr = new Date().toLocaleString(undefined, {
            year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit',
        });
        const layout = createModelsReportLayout(rows, dateStr);
        const clone = layout.cloneNode(true);
        const exportContainer = document.createElement('div');
        exportContainer.className = 'admin-models-pdf-export-wrap';
        exportContainer.setAttribute('style',
            'position:absolute;left:-9999px;top:0;width:' + PDF_PAGE_WIDTH_PX + 'px;' +
            'min-height:' + PDF_PAGE_HEIGHT_PX + 'px;padding:32px 40px;background:#fff;' +
            'box-sizing:border-box;');
        const style = document.createElement('style');
        style.textContent =
            '.admin-models-pdf-export-wrap .admin-models-pdf-title{margin:0 0 6px;font-size:18px;font-weight:600;color:#111;}' +
            '.admin-models-pdf-export-wrap .admin-models-pdf-date{margin:0 0 16px;font-size:11px;color:#6b7280;}' +
            PDF_EXPORT_TABLE_STYLES;
        exportContainer.appendChild(style);
        exportContainer.appendChild(clone);
        document.body.appendChild(exportContainer);

        try {
            if (typeof document.fonts !== 'undefined' && document.fonts.ready) {
                await document.fonts.ready;
            }
            const scale = 2;
            const canvas = await html2canvas(exportContainer, {
                scale,
                useCORS: true,
                logging: false,
                backgroundColor: '#ffffff',
                windowWidth: PDF_PAGE_WIDTH_PX + 80,
                windowHeight: exportContainer.scrollHeight + 100,
            });
            exportContainer.remove();
            const pxToMm = 0.264583333;
            const imgWmm = (canvas.width / scale) * pxToMm;
            const imgHmm = (canvas.height / scale) * pxToMm;
            const doc = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' });
            const pageW = doc.internal.pageSize.getWidth();
            const pageH = doc.internal.pageSize.getHeight();
            const w = pageW;
            const h = imgHmm * (pageW / imgWmm);
            if (h <= pageH) {
                doc.addImage(canvas.toDataURL('image/png'), 'PNG', 0, 0, w, h);
            } else {
                const totalPages = Math.ceil(h / pageH);
                const sliceHeightPx = (canvas.height / scale) * (pageH / h);
                for (let p = 0; p < totalPages; p++) {
                    if (p > 0) doc.addPage([pageW, pageH], 'l');
                    const sy = p * sliceHeightPx * scale;
                    const scy = Math.min(sliceHeightPx * scale, canvas.height - sy);
                    const sliceCanvas = document.createElement('canvas');
                    sliceCanvas.width = canvas.width;
                    sliceCanvas.height = scy;
                    const ctx = sliceCanvas.getContext('2d');
                    ctx.fillStyle = '#ffffff';
                    ctx.fillRect(0, 0, sliceCanvas.width, sliceCanvas.height);
                    ctx.drawImage(canvas, 0, sy, canvas.width, scy, 0, 0, canvas.width, scy);
                    const sliceHmm = (scy / scale) * pxToMm;
                    doc.addImage(sliceCanvas.toDataURL('image/png'), 'PNG', 0, 0, w, Math.min(sliceHmm, pageH));
                }
            }
            const filename = 'TOCSEA-models-' + new Date().toISOString().slice(0, 10) + '.pdf';
            doc.save(filename);
        } finally {
            if (exportContainer.parentNode) exportContainer.remove();
        }
    } catch (err) {
        console.error('PDF export failed:', err);
        showAlert('Failed to export PDF. Please try again.', true);
    }
}

function toggleModelsExportDropdown(open) {
    const btn = document.getElementById('adminModelsExportDropdownBtn');
    const menu = document.getElementById('adminModelsExportDropdownMenu');
    if (!btn || !menu) return;
    const isOpen = open ?? !menu.classList.contains('is-open');
    menu.classList.toggle('is-open', isOpen);
    btn.setAttribute('aria-expanded', String(isOpen));
    menu.setAttribute('aria-hidden', String(!isOpen));
}

function closeModelsExportDropdown() {
    toggleModelsExportDropdown(false);
}

function updateModelsExcelLink() {
    const params = new URLSearchParams(window.location.search);
    params.delete('page');
    params.delete('pageSize');
    const qs = params.toString();
    const base = window.location.origin + '/admin/models/export/excel';
    const excelLink = document.getElementById('adminModelsExcelBtn');
    if (excelLink && excelLink.href !== undefined) excelLink.href = base + (qs ? '?' + qs : '');
}

document.addEventListener('DOMContentLoaded', () => {
    createIcons({ icons: ADMIN_MODELS_ICONS, attrs: { 'stroke-width': 2 } });

    fetchModels();

    bindModalClose('adminViewModelModal');
    bindModalClose('adminDeleteModelModal');

    document.getElementById('adminDeleteModelConfirmBtn')?.addEventListener('click', deleteModel);

    document.getElementById('adminModelsExportDropdownBtn')?.addEventListener('click', (e) => {
        e.stopPropagation();
        toggleModelsExportDropdown();
    });

    document.addEventListener('click', () => closeModelsExportDropdown());

    document.getElementById('adminModelsExportDropdownMenu')?.addEventListener('click', (e) => {
        e.stopPropagation();
    });

    document.getElementById('adminModelsPrintBtn')?.addEventListener('click', () => {
        closeModelsExportDropdown();
        const dateEl = document.getElementById('adminModelsPrintDate');
        if (dateEl) dateEl.textContent = new Date().toLocaleString();
        window.print();
    });

    document.getElementById('adminModelsPdfBtn')?.addEventListener('click', async (e) => {
        e.preventDefault();
        closeModelsExportDropdown();
        await exportModelsToPdf();
    });

    updateModelsExcelLink();
    const filterForm = document.getElementById('adminModelsFilterForm');
    if (filterForm) filterForm.addEventListener('submit', () => {
        closeModelsExportDropdown();
        updateModelsExcelLink();
    });

    document.querySelectorAll('.fade-in-element').forEach((el, i) => {
        setTimeout(() => el.classList.add('is-visible'), i * 50);
    });
});
