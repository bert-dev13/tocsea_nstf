/**
 * TOCSEA Admin Calculation Monitoring - View modal, Delete, Paginated List, Export (PDF, Excel, Print)
 */

import { usePagination, renderAdminPagination } from './pagination.js';
import { jsPDF } from 'jspdf';
import autoTable from 'jspdf-autotable';
import {
    createIcons,
    AlertCircle,
    AlertTriangle,
    BarChart2,
    Calculator,
    ChevronDown,
    CircleCheckBig,
    Download,
    Eye,
    FileSpreadsheet,
    FileText,
    LayoutDashboard,
    Layers,
    Loader2,
    LogOut,
    PanelLeft,
    Printer,
    Search,
    Settings,
    Trash2,
    User,
    Users,
    X,
} from 'lucide';

const ADMIN_CALC_ICONS = {
    AlertCircle,
    AlertTriangle,
    BarChart2,
    Calculator,
    ChevronDown,
    CircleCheckBig,
    Download,
    Eye,
    FileSpreadsheet,
    FileText,
    LayoutDashboard,
    Layers,
    Loader2,
    LogOut,
    PanelLeft,
    Printer,
    Search,
    Settings,
    Trash2,
    User,
    Users,
    X,
};

const API_CALCULATIONS_URL = '/api/admin/calculations';

function getConfig() {
    return window.ADMIN_CALCULATIONS_CONFIG || {};
}

function getQueryParams() {
    const params = new URLSearchParams(window.location.search);
    return {
        page: Math.max(1, parseInt(params.get('page'), 10) || 1),
        pageSize: Math.min(100, Math.max(1, parseInt(params.get('pageSize'), 10) || 25)),
        search: params.get('search') || '',
        from: params.get('from') || params.get('dateFrom') || '',
        to: params.get('to') || params.get('dateTo') || '',
        location: params.get('location') || '',
        risk: params.get('risk') || '',
        equation: params.get('equation') || params.get('modelId') || '',
        sort: params.get('sort') || 'newest',
    };
}

function buildCalculationsApiUrl(params) {
    const p = new URLSearchParams();
    if (params.page) p.set('page', params.page);
    if (params.pageSize) p.set('pageSize', params.pageSize);
    if (params.search) p.set('search', params.search);
    if (params.from) p.set('from', params.from);
    if (params.to) p.set('to', params.to);
    if (params.location) p.set('location', params.location);
    if (params.risk) p.set('risk', params.risk);
    if (params.equation) p.set('equation', params.equation);
    if (params.sort) p.set('sort', params.sort);
    return API_CALCULATIONS_URL + (p.toString() ? '?' + p.toString() : '');
}

function updateUrl(params) {
    const url = new URL(window.location);
    Object.entries(params).forEach(([k, v]) => {
        if (v === '' || v == null) url.searchParams.delete(k);
        else url.searchParams.set(k, String(v));
    });
    window.history.replaceState({}, '', url.toString());
}

function renderCalculationRow(calc) {
    const riskClass = calc.risk_class || 'na';
    return `
        <tr data-calculation-id="${calc.id}">
            <td>${escapeHtml(calc.created_at)}</td>
            <td><span class="admin-users-cell-name">${escapeHtml(calc.user_name)}</span></td>
            <td><span class="admin-users-cell-address">${escapeHtml(calc.location_display)}</span></td>
            <td>${escapeHtml(calc.equation_name)}</td>
            <td>${Number(calc.result).toFixed(2)} m²/year</td>
            <td><span class="admin-users-badge admin-calc-badge-risk admin-calc-badge-risk-${riskClass}">${escapeHtml(calc.risk_level)}</span></td>
            <td><span class="admin-users-badge admin-users-badge-status-active">Completed</span></td>
            <td class="admin-users-col-actions no-print">
                <div class="admin-users-actions">
                    <button type="button" class="admin-users-action-btn admin-users-action-view admin-calc-btn-view" data-calculation-id="${calc.id}" aria-label="View calculation">
                        <i data-lucide="eye" class="lucide-icon lucide-icon-sm"></i>
                    </button>
                    <button type="button" class="admin-users-action-btn admin-users-action-delete admin-calc-btn-delete" data-calculation-id="${calc.id}" data-equation-name="${escapeHtml(calc.equation_name)}" aria-label="Delete calculation">
                        <i data-lucide="trash-2" class="lucide-icon lucide-icon-sm"></i>
                    </button>
                </div>
            </td>
        </tr>
    `;
}

async function fetchCalculations() {
    const params = getQueryParams();
    const skeleton = document.getElementById('adminCalculationsTableSkeleton');
    const emptyEl = document.getElementById('adminCalculationsEmpty');
    const table = document.getElementById('adminCalculationsTable');
    const tbody = document.getElementById('adminCalculationsTableBody');
    const paginationContainer = document.getElementById('adminCalculationsPagination');

    skeleton.style.display = '';
    emptyEl.style.display = 'none';
    table.style.display = 'none';
    if (tbody) tbody.innerHTML = '';
    paginationContainer.innerHTML = '';

    try {
        const url = buildCalculationsApiUrl(params);
        const res = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        if (!res.ok) throw new Error('Failed to load calculations');
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
            createIcons({ icons: ADMIN_CALC_ICONS, attrs: { 'stroke-width': 2 } });
        } else {
            table.style.display = '';
            tbody.innerHTML = data.map(renderCalculationRow).join('');
            bindRowActions(tbody);
            createIcons({ icons: ADMIN_CALC_ICONS, attrs: { 'stroke-width': 2 } });
        }

        renderAdminPagination(paginationContainer, meta, {
            loading: false,
            containerId: 'admin-calculations-pagination',
            onPageChange: (page) => {
                updateUrl({ ...params, page });
                fetchCalculations();
            },
            onPageSizeChange: (pageSize) => {
                updateUrl({ ...params, page: 1, pageSize });
                fetchCalculations();
            },
        });
    } catch (err) {
        skeleton.style.display = 'none';
        emptyEl.style.display = '';
        const p = emptyEl.querySelector('p');
        if (p) p.textContent = 'Failed to load calculations. Please try again.';
        const hint = emptyEl.querySelector('.admin-users-empty-hint');
        if (hint) hint.textContent = 'Check your connection and refresh.';
        createIcons({ icons: ADMIN_CALC_ICONS, attrs: { 'stroke-width': 2 } });
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

function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (!modal) return;
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
    createIcons({ icons: ADMIN_CALC_ICONS, attrs: { 'stroke-width': 2 } });
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

function escapeHtml(str) {
    if (str == null) return '';
    const div = document.createElement('div');
    div.textContent = String(str);
    return div.innerHTML;
}

/**
 * Decode HTML entities (e.g. &times;, &minus;, &nbsp;) to plain Unicode so formula
 * displays correctly in modal, PDF, and print. Use on formula_snapshot before escapeHtml.
 */
function decodeHtmlEntities(str) {
    if (str == null || str === '') return '';
    const div = document.createElement('div');
    div.innerHTML = String(str);
    return div.textContent || div.innerText || '';
}

function formatInputs(inputs) {
    if (!inputs || typeof inputs !== 'object') return '—';
    const entries = Array.isArray(inputs)
        ? inputs.map((v, i) => [`Input ${i + 1}`, v])
        : Object.entries(inputs);
    return entries
        .map(([k, v]) => {
            const val = typeof v === 'object' ? JSON.stringify(v) : v;
            return `<div class="admin-view-calculation-row"><dt>${escapeHtml(k)}</dt><dd>${escapeHtml(val)}</dd></div>`;
        })
        .join('');
}

function renderViewContent(data) {
    const riskClass = (data.risk_level || '—').toLowerCase();
    const rawFormula = (data.formula_snapshot || '').trim();
    const formula = rawFormula ? escapeHtml(decodeHtmlEntities(rawFormula)) : '—';
    const inputsHtml = formatInputs(data.inputs);
    return `
        <div class="admin-view-calculation-grid">
            <div class="admin-view-calculation-row">
                <dt>User</dt>
                <dd>${escapeHtml(data.user_name || '—')} ${data.user_email ? `<br><span style="font-size:0.875rem;color:var(--color-text-muted);">${escapeHtml(data.user_email)}</span>` : ''}</dd>
            </div>
            <div class="admin-view-calculation-row">
                <dt>Location</dt>
                <dd>${escapeHtml(data.location_display || '—')}</dd>
            </div>
            <div class="admin-view-calculation-row">
                <dt>Model/Equation</dt>
                <dd>${escapeHtml(data.equation_name || '—')}</dd>
            </div>
            <div class="admin-view-calculation-row">
                <dt>Predicted Soil Loss</dt>
                <dd>${escapeHtml(data.result_formatted || data.result + ' m²/year')}</dd>
            </div>
            <div class="admin-view-calculation-row">
                <dt>Risk Level</dt>
                <dd><span class="admin-users-badge admin-calc-badge-risk admin-calc-badge-risk-${riskClass === '—' ? 'na' : riskClass}">${escapeHtml(data.risk_level || '—')}</span></dd>
            </div>
            <div class="admin-view-calculation-row">
                <dt>Formula</dt>
                <dd class="admin-view-calculation-formula">${formula}</dd>
            </div>
            <div class="admin-view-calculation-row">
                <dt>Inputs</dt>
                <dd><div class="admin-view-calculation-grid">${inputsHtml}</div></dd>
            </div>
            ${data.notes ? `<div class="admin-view-calculation-row"><dt>Notes</dt><dd>${escapeHtml(data.notes)}</dd></div>` : ''}
        </div>
    `;
}

async function handleView(e) {
    const id = e.currentTarget.dataset.calculationId;
    if (!id) return;
    const contentEl = document.getElementById('adminViewCalculationContent');
    const subtitleEl = document.getElementById('adminViewCalculationSubtitle');
    if (!contentEl) return;

    const routes = getConfig().routes || {};
    const showUrl = (routes.show || '').replace('__ID__', id);

    openModal('adminViewCalculationModal');
    if (subtitleEl) subtitleEl.textContent = 'Viewing calculation #' + id;
    contentEl.innerHTML = `
        <div class="admin-modal-loading">
            <i data-lucide="loader-2" class="lucide-icon lucide-icon-lg admin-spin" aria-hidden="true"></i>
            <span>Loading…</span>
        </div>
    `;
    createIcons({ icons: ADMIN_CALC_ICONS, attrs: { 'stroke-width': 2 } });

    try {
        const res = await fetch(showUrl, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        if (!res.ok) throw new Error(res.statusText);
        const data = await res.json();
        contentEl.innerHTML = renderViewContent(data);
    } catch {
        contentEl.innerHTML = '<p class="admin-modal-error">Failed to load calculation details. Please try again.</p>';
    }
    createIcons({ icons: ADMIN_CALC_ICONS, attrs: { 'stroke-width': 2 } });
}

let currentDeleteId = null;

function handleDelete(e) {
    const id = e.currentTarget.dataset.calculationId;
    const equationName = e.currentTarget.dataset.equationName || '—';
    if (!id) return;
    currentDeleteId = id;
    const dd = document.getElementById('adminDeleteCalculationEquation');
    if (dd) dd.textContent = equationName;
    openModal('adminDeleteCalculationModal');
}

async function confirmDelete() {
    if (!currentDeleteId) return;
    const routes = getConfig().routes || {};
    const destroyUrl = (routes.destroy || '').replace('__ID__', currentDeleteId);
    const btn = document.getElementById('adminDeleteCalculationConfirmBtn');
    if (btn) btn.disabled = true;

    try {
        const res = await fetch(destroyUrl, {
            method: 'DELETE',
            headers: getCsrfHeaders(),
            credentials: 'same-origin',
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) throw new Error(data.message || res.statusText);
        closeModal('adminDeleteCalculationModal');
        fetchCalculations();
        const alertEl = document.getElementById('adminCalculationsAlert');
        const alertText = document.getElementById('adminCalculationsAlertText');
        if (alertEl && alertText) {
            alertText.textContent = data.message || 'Calculation deleted successfully.';
            alertEl.style.display = 'flex';
            setTimeout(() => { alertEl.style.display = 'none'; }, 3000);
        }
    } catch (err) {
        const alertEl = document.getElementById('adminCalculationsErrorAlert');
        const alertText = document.getElementById('adminCalculationsErrorAlertText');
        if (alertEl && alertText) {
            alertText.textContent = err.message || 'Failed to delete calculation.';
            alertEl.style.display = 'flex';
        }
    } finally {
        currentDeleteId = null;
        if (btn) btn.disabled = false;
    }
}

function bindRowActions(container) {
    const scope = container || document;
    scope.querySelectorAll('.admin-calc-btn-view').forEach(btn => {
        btn.addEventListener('click', handleView);
    });
    scope.querySelectorAll('.admin-calc-btn-delete').forEach(btn => {
        btn.addEventListener('click', handleDelete);
    });
}

function showCalculationsAlert(message, isError = false) {
    const alertEl = document.getElementById(isError ? 'adminCalculationsErrorAlert' : 'adminCalculationsAlert');
    const alertText = document.getElementById(isError ? 'adminCalculationsErrorAlertText' : 'adminCalculationsAlertText');
    if (alertEl && alertText) {
        alertText.textContent = message;
        alertEl.style.display = 'flex';
        if (isError) {
            document.getElementById('adminCalculationsAlert')?.style.setProperty('display', 'none');
        } else {
            document.getElementById('adminCalculationsErrorAlert')?.style.setProperty('display', 'none');
        }
        setTimeout(() => { alertEl.style.display = 'none'; }, 4000);
    }
}

async function fetchAllCalculationsForExport() {
    const params = { ...getQueryParams(), page: 1, pageSize: 100 };
    const all = [];
    let hasMore = true;
    while (hasMore) {
        const url = buildCalculationsApiUrl(params);
        const res = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        if (!res.ok) throw new Error('Failed to load calculations');
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

async function exportCalculationsToPdf() {
    try {
        const rows = await fetchAllCalculationsForExport();
        if (rows.length === 0) {
            showCalculationsAlert('No calculations to export.', true);
            return;
        }

        const doc = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' });
        const reportTitle = 'TOCSEA – Calculation Monitoring Report';
        const dateStr = new Date().toLocaleString(undefined, {
            year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit',
        });

        doc.setFontSize(16);
        doc.text(reportTitle, 14, 15);
        doc.setFontSize(10);
        doc.text('Generated: ' + dateStr, 14, 22);

        const headers = ['Date/Time', 'User', 'Location', 'Model/Equation', 'Predicted Soil Loss', 'Risk Level', 'Status'];
        const body = rows.map((r) => [
            r.created_at || '',
            r.user_name || '—',
            r.location_display || '—',
            r.equation_name || '—',
            (Number(r.result) != null ? Number(r.result).toFixed(2) : '') + ' m²/year',
            r.risk_level || '—',
            'Completed',
        ]);

        autoTable(doc, {
            head: [headers],
            body,
            startY: 28,
            styles: { fontSize: 8 },
            headStyles: { fillColor: [66, 66, 66] },
        });

        const filename = 'TOCSEA-calculations-' + new Date().toISOString().slice(0, 10) + '.pdf';
        doc.save(filename);
    } catch (err) {
        console.error('PDF export failed:', err);
        showCalculationsAlert('Failed to export PDF. Please try again.', true);
    }
}

function toggleCalculationsExportDropdown(open) {
    const btn = document.getElementById('adminCalculationsExportDropdownBtn');
    const menu = document.getElementById('adminCalculationsExportDropdownMenu');
    if (!btn || !menu) return;
    const isOpen = open ?? !menu.classList.contains('is-open');
    menu.classList.toggle('is-open', isOpen);
    btn.setAttribute('aria-expanded', String(isOpen));
    menu.setAttribute('aria-hidden', String(!isOpen));
}

function closeCalculationsExportDropdown() {
    toggleCalculationsExportDropdown(false);
}

function updateCalculationsExcelLink() {
    const params = new URLSearchParams(window.location.search);
    params.delete('page');
    params.delete('pageSize');
    const qs = params.toString();
    const base = window.location.origin + '/admin/calculations/export/excel';
    const excelLink = document.getElementById('adminCalculationsExcelBtn');
    if (excelLink && excelLink.href !== undefined) excelLink.href = base + (qs ? '?' + qs : '');
}

document.addEventListener('DOMContentLoaded', () => {
    fetchCalculations();

    bindModalClose('adminViewCalculationModal');
    bindModalClose('adminDeleteCalculationModal');

    document.getElementById('adminDeleteCalculationConfirmBtn')?.addEventListener('click', confirmDelete);

    document.getElementById('adminCalculationsExportDropdownBtn')?.addEventListener('click', (e) => {
        e.stopPropagation();
        toggleCalculationsExportDropdown();
    });

    document.addEventListener('click', () => closeCalculationsExportDropdown());

    document.getElementById('adminCalculationsExportDropdownMenu')?.addEventListener('click', (e) => {
        e.stopPropagation();
    });

    document.getElementById('adminCalculationsPrintBtn')?.addEventListener('click', () => {
        closeCalculationsExportDropdown();
        const dateEl = document.getElementById('adminCalculationsPrintDate');
        if (dateEl) dateEl.textContent = new Date().toLocaleString();
        window.print();
    });

    document.getElementById('adminCalculationsPdfBtn')?.addEventListener('click', async () => {
        closeCalculationsExportDropdown();
        await exportCalculationsToPdf();
    });

    updateCalculationsExcelLink();
    const filterForm = document.getElementById('adminCalculationsFilterForm');
    if (filterForm) filterForm.addEventListener('submit', () => { closeCalculationsExportDropdown(); updateCalculationsExcelLink(); });

    createIcons({ icons: ADMIN_CALC_ICONS, attrs: { 'stroke-width': 2 } });
});
