/**
 * TOCSEA Admin User Management - Modals, Actions, Paginated List
 */

import { usePagination, renderAdminPagination } from './pagination.js';
import { jsPDF } from 'jspdf';
import autoTable from 'jspdf-autotable';
import {
    createIcons,
    Users,
    Search,
    Eye,
    Pencil,
    Trash2,
    UserPlus,
    User,
    X,
    Loader2,
    LayoutDashboard,
    LogOut,
    PanelLeft,
    ArrowLeft,
    Calculator,
    Layers,
    Box,
    CircleCheckBig,
    AlertCircle,
    AlertTriangle,
    Printer,
    FileSpreadsheet,
    FileText,
    Download,
    ChevronDown,
} from 'lucide';

const ADMIN_USERS_ICONS = {
    Users,
    Search,
    Eye,
    Pencil,
    Trash2,
    UserPlus,
    User,
    X,
    Loader2,
    LayoutDashboard,
    LogOut,
    PanelLeft,
    ArrowLeft,
    Calculator,
    Layers,
    Box,
    CircleCheckBig,
    AlertCircle,
    AlertTriangle,
    Printer,
    FileSpreadsheet,
    FileText,
    Download,
    ChevronDown,
};

function replaceUsersIcons() {
    const root = document.querySelector('.admin-users-page');
    if (root) createIcons({ icons: ADMIN_USERS_ICONS, attrs: { 'stroke-width': 2 }, root });
}

const API_USERS_URL = '/api/admin/users';

function getConfig() {
    return window.ADMIN_USERS_CONFIG || {};
}

function getQueryParams() {
    const params = new URLSearchParams(window.location.search);
    return {
        page: Math.max(1, parseInt(params.get('page'), 10) || 1),
        pageSize: Math.min(100, Math.max(1, parseInt(params.get('pageSize'), 10) || 25)),
        search: params.get('search') || '',
        role: params.get('role') || '',
        status: params.get('status') || '',
        sort: params.get('sort') || 'newest',
    };
}

function buildUsersApiUrl(params) {
    const p = new URLSearchParams();
    if (params.page) p.set('page', params.page);
    if (params.pageSize) p.set('pageSize', params.pageSize);
    if (params.search) p.set('search', params.search);
    if (params.role) p.set('role', params.role);
    if (params.status) p.set('status', params.status);
    if (params.sort) p.set('sort', params.sort);
    return API_USERS_URL + (p.toString() ? '?' + p.toString() : '');
}

function updateUrl(params) {
    const url = new URL(window.location);
    Object.entries(params).forEach(([k, v]) => {
        if (v === '' || v == null) url.searchParams.delete(k);
        else url.searchParams.set(k, String(v));
    });
    window.history.replaceState({}, '', url.toString());
}

function renderUserRow(user) {
    const provinceDisplay = user.province ? escapeHtml(user.province) : '<span class="admin-users-cell-muted">—</span>';
    const municipalityDisplay = user.municipality ? escapeHtml(user.municipality) : '<span class="admin-users-cell-muted">—</span>';
    const barangayDisplay = user.barangay ? escapeHtml(user.barangay) : '<span class="admin-users-cell-muted">—</span>';
    const lastLoginDisplay = user.last_login_at ? escapeHtml(user.last_login_at) : '<span class="admin-users-cell-muted">—</span>';
    const deleteBtn = user.id === parseInt(window.ADMIN_CURRENT_USER_ID, 10)
        ? `<button type="button" class="admin-users-action-btn admin-users-action-delete is-disabled" disabled aria-disabled="true"><i data-lucide="trash-2" class="lucide-icon lucide-icon-sm"></i></button>`
        : `<button type="button" class="admin-users-action-btn admin-users-action-delete" data-user-id="${user.id}" data-user-name="${escapeHtml(user.name)}" data-user-email="${escapeHtml(user.email)}" aria-label="Delete ${escapeHtml(user.name)}"><i data-lucide="trash-2" class="lucide-icon lucide-icon-sm"></i></button>`;
    return `
        <tr data-user-id="${user.id}">
            <td><span class="admin-users-cell-name">${escapeHtml(user.name)}</span></td>
            <td>${escapeHtml(user.email)}</td>
            <td><span class="admin-users-badge admin-users-badge-${user.role}">${escapeHtml(user.role_label)}</span></td>
            <td><span class="admin-users-badge admin-users-badge-status-${user.status}">${escapeHtml(user.status_label)}</span></td>
            <td>${provinceDisplay}</td>
            <td>${municipalityDisplay}</td>
            <td>${barangayDisplay}</td>
            <td>${escapeHtml(user.created_at)}</td>
            <td>${lastLoginDisplay}</td>
            <td class="admin-users-col-actions no-print">
                <div class="admin-users-actions">
                    <button type="button" class="admin-users-action-btn admin-users-action-view" data-user-id="${user.id}" aria-label="View ${escapeHtml(user.name)}">
                        <i data-lucide="eye" class="lucide-icon lucide-icon-sm"></i>
                    </button>
                    <button type="button" class="admin-users-action-btn admin-users-action-edit" data-user-id="${user.id}" data-user-name="${escapeHtml(user.name)}" data-user-email="${escapeHtml(user.email)}" data-user-role="${user.role}" data-user-status="${user.status}" data-user-province="${escapeHtml(user.province || '')}" data-user-municipality="${escapeHtml(user.municipality || '')}" data-user-barangay="${escapeHtml(user.barangay || '')}" aria-label="Edit ${escapeHtml(user.name)}">
                        <i data-lucide="pencil" class="lucide-icon lucide-icon-sm"></i>
                    </button>
                    ${deleteBtn}
                </div>
            </td>
        </tr>
    `;
}

async function fetchUsers() {
    const params = getQueryParams();
    const skeleton = document.getElementById('adminUsersTableSkeleton');
    const emptyEl = document.getElementById('adminUsersEmpty');
    const table = document.getElementById('adminUsersTable');
    const tbody = document.getElementById('adminUsersTableBody');
    const paginationContainer = document.getElementById('adminUsersPagination');

    skeleton.style.display = '';
    emptyEl.style.display = 'none';
    table.style.display = 'none';
    if (tbody) tbody.innerHTML = '';
    paginationContainer.innerHTML = '';

    try {
        const url = buildUsersApiUrl(params);
        const res = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        if (!res.ok) throw new Error('Failed to load users');
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
            replaceUsersIcons();
        } else {
            table.style.display = '';
            tbody.innerHTML = data.map(renderUserRow).join('');
            bindRowActions(tbody);
            replaceUsersIcons();
        }

        renderAdminPagination(paginationContainer, meta, {
            loading: false,
            containerId: 'admin-users-pagination',
            onPageChange: (page) => {
                updateUrl({ ...params, page });
                fetchUsers();
                if (typeof window.adminUsersUpdateExportLinks === 'function') window.adminUsersUpdateExportLinks();
            },
            onPageSizeChange: (pageSize) => {
                updateUrl({ ...params, page: 1, pageSize });
                fetchUsers();
                if (typeof window.adminUsersUpdateExportLinks === 'function') window.adminUsersUpdateExportLinks();
            },
        });
    } catch (err) {
        skeleton.style.display = 'none';
        emptyEl.style.display = '';
        emptyEl.querySelector('p').textContent = 'Failed to load users. Please try again.';
        if (emptyEl.querySelector('.admin-users-empty-hint')) {
            emptyEl.querySelector('.admin-users-empty-hint').textContent = 'Check your connection and refresh.';
        }
        replaceUsersIcons();
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

const PSGC_BASE = 'https://psgc.gitlab.io/api';

function resetPsgcSelect(select, placeholder) {
    if (!select) return;
    select.innerHTML = `<option value="">${placeholder}</option>`;
    select.disabled = true;
    select.value = '';
}

function populatePsgcOptions(select, items, placeholder) {
    if (!select) return;
    select.innerHTML = `<option value="">${placeholder}</option>`;
    (items || []).forEach(({ code, name }) => {
        const opt = document.createElement('option');
        opt.value = name;
        opt.textContent = name;
        opt.dataset.code = code;
        select.appendChild(opt);
    });
    select.disabled = false;
}

async function loadPsgcProvinces(prefix) {
    const provinceSelect = document.getElementById(`${prefix}Province`);
    const municipalitySelect = document.getElementById(`${prefix}Municipality`);
    const barangaySelect = document.getElementById(`${prefix}Barangay`);
    if (!provinceSelect) return;
    resetPsgcSelect(municipalitySelect, 'Select Municipality');
    resetPsgcSelect(barangaySelect, 'Select Barangay');
    try {
        const res = await fetch(`${PSGC_BASE}/provinces/`);
        if (!res.ok) throw new Error('Failed to load provinces');
        const data = await res.json();
        populatePsgcOptions(provinceSelect, data, 'Select Province');
    } catch {
        provinceSelect.innerHTML = '<option value="">Error loading provinces</option>';
    }
}

async function loadPsgcMunicipalities(prefix, provinceCode) {
    const municipalitySelect = document.getElementById(`${prefix}Municipality`);
    const barangaySelect = document.getElementById(`${prefix}Barangay`);
    if (!municipalitySelect) return;
    resetPsgcSelect(municipalitySelect, 'Select Municipality');
    resetPsgcSelect(barangaySelect, 'Select Barangay');
    if (!provinceCode) return;
    try {
        const res = await fetch(`${PSGC_BASE}/provinces/${provinceCode}/municipalities/`);
        if (!res.ok) throw new Error('Failed to load municipalities');
        const data = await res.json();
        populatePsgcOptions(municipalitySelect, data, 'Select Municipality');
    } catch {
        municipalitySelect.innerHTML = '<option value="">Error loading municipalities</option>';
    }
}

async function loadPsgcBarangays(prefix, municipalityCode) {
    const barangaySelect = document.getElementById(`${prefix}Barangay`);
    if (!barangaySelect) return;
    resetPsgcSelect(barangaySelect, 'Select Barangay');
    if (!municipalityCode) return;
    try {
        const res = await fetch(`${PSGC_BASE}/municipalities/${municipalityCode}/barangays/`);
        if (!res.ok) throw new Error('Failed to load barangays');
        const data = await res.json();
        populatePsgcOptions(barangaySelect, data, 'Select Barangay');
    } catch {
        barangaySelect.innerHTML = '<option value="">Error loading barangays</option>';
    }
}

async function initPsgcForEditModal(provinceName, municipalityName, barangayName) {
    await loadPsgcProvinces('adminEdit');
    const provinceSelect = document.getElementById('adminEditProvince');
    const municipalitySelect = document.getElementById('adminEditMunicipality');
    const barangaySelect = document.getElementById('adminEditBarangay');
    if (provinceName && provinceSelect) {
        provinceSelect.value = provinceName;
        const opt = provinceSelect.selectedOptions[0];
        const provinceCode = opt?.dataset?.code;
        if (provinceCode) {
            await loadPsgcMunicipalities('adminEdit', provinceCode);
            if (municipalityName && municipalitySelect) {
                municipalitySelect.value = municipalityName;
                const munOpt = municipalitySelect.selectedOptions[0];
                const municipalityCode = munOpt?.dataset?.code;
                if (municipalityCode) {
                    await loadPsgcBarangays('adminEdit', municipalityCode);
                    if (barangayName && barangaySelect) barangaySelect.value = barangayName;
                }
            }
        }
    }
}

function bindPsgcCascade(prefix) {
    const provinceSelect = document.getElementById(`${prefix}Province`);
    const municipalitySelect = document.getElementById(`${prefix}Municipality`);
    const barangaySelect = document.getElementById(`${prefix}Barangay`);
    if (!provinceSelect || !municipalitySelect || !barangaySelect) return;
    provinceSelect.addEventListener('change', () => {
        const opt = provinceSelect.selectedOptions[0];
        const code = opt?.dataset?.code;
        if (code) loadPsgcMunicipalities(prefix, code);
        else {
            resetPsgcSelect(municipalitySelect, 'Select Municipality');
            resetPsgcSelect(barangaySelect, 'Select Barangay');
        }
    });
    municipalitySelect.addEventListener('change', () => {
        const opt = municipalitySelect.selectedOptions[0];
        const code = opt?.dataset?.code;
        if (code) loadPsgcBarangays(prefix, code);
        else resetPsgcSelect(barangaySelect, 'Select Barangay');
    });
}

let alertAutoHideTimer = null;

function showAlert(message, isError = false) {
    const alertEl = document.getElementById('adminUsersAlert');
    const errorEl = document.getElementById('adminUsersErrorAlert');
    const alertText = document.getElementById('adminUsersAlertText');
    const errorText = document.getElementById('adminUsersErrorAlertText');
    if (!alertEl || !errorEl) return;
    if (alertAutoHideTimer) {
        clearTimeout(alertAutoHideTimer);
        alertAutoHideTimer = null;
    }
    alertEl.style.display = isError ? 'none' : 'flex';
    errorEl.style.display = isError ? 'flex' : 'none';
    if (alertText) alertText.textContent = message;
    if (errorText) errorText.textContent = message;
    alertEl.classList.remove('admin-alert-popup-visible');
    errorEl.classList.remove('admin-alert-popup-visible');
    void alertEl.offsetWidth;
    if (!isError) {
        void alertEl.offsetWidth;
        alertEl.classList.add('admin-alert-popup-visible');
        alertAutoHideTimer = setTimeout(() => {
            alertEl.classList.remove('admin-alert-popup-visible');
            alertAutoHideTimer = setTimeout(() => {
                alertEl.style.display = 'none';
                alertAutoHideTimer = null;
            }, 300);
        }, 2500);
    } else {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
    replaceUsersIcons();
}

function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (!modal) return;
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
    replaceUsersIcons();
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
    const closeBtns = modal.querySelectorAll('[data-close-modal]');
    closeBtns.forEach(btn => {
        btn.addEventListener('click', () => closeModal(modalId));
    });
    modal.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeModal(modalId);
    });
}

function renderViewContent(data) {
    const province = (data.province && data.province.trim()) ? escapeHtml(data.province) : '—';
    const municipality = (data.municipality && data.municipality.trim()) ? escapeHtml(data.municipality) : '—';
    const barangay = (data.barangay && data.barangay.trim()) ? escapeHtml(data.barangay) : '—';
    const lastLogin = data.last_login_at
        ? escapeHtml(data.last_login_at)
        : 'Not provided';
    const calc = data.calculation_histories_count ?? 0;
    const soil = data.soil_loss_records_count ?? 0;
    const regress = data.regression_models_count ?? 0;
    return `
        <div class="admin-view-user-grid">
            <div class="admin-view-user-field">
                <span class="admin-view-user-label">Full Name</span>
                <span class="admin-view-user-value">${escapeHtml(data.name)}</span>
            </div>
            <div class="admin-view-user-field">
                <span class="admin-view-user-label">Email</span>
                <span class="admin-view-user-value">${escapeHtml(data.email)}</span>
            </div>
            <div class="admin-view-user-field">
                <span class="admin-view-user-label">Role</span>
                <span class="admin-view-user-value"><span class="admin-users-badge admin-users-badge-${escapeHtml(data.role)}">${escapeHtml(data.role_label)}</span></span>
            </div>
            <div class="admin-view-user-field">
                <span class="admin-view-user-label">Status</span>
                <span class="admin-view-user-value"><span class="admin-users-badge admin-users-badge-status-${escapeHtml(data.status)}">${escapeHtml(data.status_label)}</span></span>
            </div>
            <div class="admin-view-user-field">
                <span class="admin-view-user-label">Province</span>
                <span class="admin-view-user-value">${province}</span>
            </div>
            <div class="admin-view-user-field">
                <span class="admin-view-user-label">Municipality/City</span>
                <span class="admin-view-user-value">${municipality}</span>
            </div>
            <div class="admin-view-user-field admin-view-user-field-full">
                <span class="admin-view-user-label">Barangay</span>
                <span class="admin-view-user-value">${barangay}</span>
            </div>
            <div class="admin-view-user-divider"></div>
            <div class="admin-view-user-field admin-view-user-field-full">
                <span class="admin-view-user-label">Date Created</span>
                <span class="admin-view-user-value">${escapeHtml(data.created_at)}</span>
            </div>
            <div class="admin-view-user-field">
                <span class="admin-view-user-label">Last Login</span>
                <span class="admin-view-user-value">${lastLogin}</span>
            </div>
            <div class="admin-view-user-field">
                <span class="admin-view-user-label">Calculations</span>
                <span class="admin-view-user-value">${calc}</span>
            </div>
            <div class="admin-view-user-field">
                <span class="admin-view-user-label">Soil Loss Records</span>
                <span class="admin-view-user-value">${soil}</span>
            </div>
            <div class="admin-view-user-field">
                <span class="admin-view-user-label">Regression Models</span>
                <span class="admin-view-user-value">${regress}</span>
            </div>
        </div>
    `;
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

/**
 * Fetch all users for the current filters (paginates if needed).
 */
async function fetchAllUsersForExport() {
    const params = { ...getQueryParams(), page: 1, pageSize: 100 };
    const all = [];
    let hasMore = true;
    while (hasMore) {
        const url = buildUsersApiUrl(params);
        const res = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        if (!res.ok) throw new Error('Failed to load users');
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
 * Export users to PDF using jsPDF + jspdf-autotable. Triggers immediate download.
 */
async function exportUsersToPdf() {
    try {
        const users = await fetchAllUsersForExport();
        if (users.length === 0) {
            showAlert('No users to export.', true);
            return;
        }

        const doc = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' });
        const reportTitle = 'TOCSEA – User Management Report';
        const dateStr = new Date().toLocaleString(undefined, {
            year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit',
        });

        doc.setFontSize(16);
        doc.text(reportTitle, 14, 15);
        doc.setFontSize(10);
        doc.text('Generated: ' + dateStr, 14, 22);

        const headers = ['Name', 'Email', 'Role', 'Status', 'Province', 'Municipality', 'Barangay', 'Date Created', 'Last Login'];
        const body = users.map((u) => [
            u.name || '',
            u.email || '',
            u.role_label || u.role || '',
            u.status_label || u.status || '',
            u.province || '—',
            u.municipality || '—',
            u.barangay || '—',
            u.created_at || '',
            u.last_login_at || '—',
        ]);

        autoTable(doc, {
            head: [headers],
            body,
            startY: 28,
            styles: { fontSize: 8 },
            headStyles: { fillColor: [66, 66, 66] },
        });

        const filename = 'TOCSEA-users-' + new Date().toISOString().slice(0, 10) + '.pdf';
        doc.save(filename);
    } catch (err) {
        console.error('PDF export failed:', err);
        showAlert('Failed to export PDF. Please try again.', true);
    }
}

function updateTableRow(userId, user) {
    const row = document.querySelector(`tr[data-user-id="${userId}"]`);
    if (!row) return;
    const nameCell = row.querySelector('.admin-users-cell-name');
    const emailCell = row.cells[1];
    const roleCell = row.cells[2];
    const statusCell = row.cells[3];
    const provinceCell = row.cells[4];
    const municipalityCell = row.cells[5];
    const barangayCell = row.cells[6];
    const dateCell = row.cells[7];
    const lastLoginCell = row.cells[8];
    if (nameCell) nameCell.textContent = user.name;
    if (emailCell) emailCell.textContent = user.email;
    if (roleCell) {
        roleCell.innerHTML = `<span class="admin-users-badge admin-users-badge-${user.role}">${escapeHtml(user.role_label)}</span>`;
    }
    if (statusCell) {
        statusCell.innerHTML = `<span class="admin-users-badge admin-users-badge-status-${user.status}">${escapeHtml(user.status_label)}</span>`;
    }
    if (provinceCell) provinceCell.innerHTML = user.province ? escapeHtml(user.province) : '<span class="admin-users-cell-muted">—</span>';
    if (municipalityCell) municipalityCell.innerHTML = user.municipality ? escapeHtml(user.municipality) : '<span class="admin-users-cell-muted">—</span>';
    if (barangayCell) barangayCell.innerHTML = user.barangay ? escapeHtml(user.barangay) : '<span class="admin-users-cell-muted">—</span>';
    if (dateCell) dateCell.textContent = user.created_at;
    if (lastLoginCell) lastLoginCell.innerHTML = user.last_login_at ? escapeHtml(user.last_login_at) : '<span class="admin-users-cell-muted">—</span>';
}

function addTableRow(user) {
    let tbody = document.querySelector('.admin-users-table tbody');
    const emptyEl = document.querySelector('.admin-users-empty');
    if (!tbody && emptyEl) {
        const wrap = emptyEl.closest('.admin-users-table-wrap');
        emptyEl.remove();
        wrap.insertAdjacentHTML('beforeend', `
            <table class="admin-users-table" role="grid">
                <thead>
                    <tr>
                        <th scope="col">Name</th>
                        <th scope="col">Email</th>
                        <th scope="col">Role</th>
                        <th scope="col">Status</th>
                        <th scope="col">Province</th>
                        <th scope="col">Municipality/City</th>
                        <th scope="col">Barangay</th>
                        <th scope="col">Date Created</th>
                        <th scope="col">Last Login</th>
                        <th scope="col" class="admin-users-col-actions">Actions</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        `);
        tbody = document.querySelector('.admin-users-table tbody');
    }
    if (!tbody) return;
    const provinceDisplay = user.province ? escapeHtml(user.province) : '<span class="admin-users-cell-muted">—</span>';
    const municipalityDisplay = user.municipality ? escapeHtml(user.municipality) : '<span class="admin-users-cell-muted">—</span>';
    const barangayDisplay = user.barangay ? escapeHtml(user.barangay) : '<span class="admin-users-cell-muted">—</span>';
    const lastLoginDisplay = user.last_login_at ? escapeHtml(user.last_login_at) : '<span class="admin-users-cell-muted">—</span>';
    const actions = user.id === window.ADMIN_CURRENT_USER_ID
        ? `<button type="button" class="admin-users-action-btn admin-users-action-delete is-disabled" disabled aria-disabled="true"><i data-lucide="trash-2" class="lucide-icon lucide-icon-sm"></i></button>`
        : `<button type="button" class="admin-users-action-btn admin-users-action-delete" data-user-id="${user.id}" data-user-name="${escapeHtml(user.name)}" data-user-email="${escapeHtml(user.email)}" aria-label="Delete ${escapeHtml(user.name)}"><i data-lucide="trash-2" class="lucide-icon lucide-icon-sm"></i></button>`;
    const tr = document.createElement('tr');
    tr.dataset.userId = user.id;
    tr.innerHTML = `
        <td><span class="admin-users-cell-name">${escapeHtml(user.name)}</span></td>
        <td>${escapeHtml(user.email)}</td>
        <td><span class="admin-users-badge admin-users-badge-${user.role}">${escapeHtml(user.role_label)}</span></td>
        <td><span class="admin-users-badge admin-users-badge-status-${user.status}">${escapeHtml(user.status_label)}</span></td>
        <td>${provinceDisplay}</td>
        <td>${municipalityDisplay}</td>
        <td>${barangayDisplay}</td>
        <td>${escapeHtml(user.created_at)}</td>
        <td>${lastLoginDisplay}</td>
        <td class="admin-users-col-actions no-print">
            <div class="admin-users-actions">
                <button type="button" class="admin-users-action-btn admin-users-action-view" data-user-id="${user.id}" aria-label="View ${escapeHtml(user.name)}">
                    <i data-lucide="eye" class="lucide-icon lucide-icon-sm"></i>
                </button>
                <button type="button" class="admin-users-action-btn admin-users-action-edit" data-user-id="${user.id}" data-user-name="${escapeHtml(user.name)}" data-user-email="${escapeHtml(user.email)}" data-user-role="${user.role}" data-user-status="${user.status}" data-user-province="${escapeHtml(user.province || '')}" data-user-municipality="${escapeHtml(user.municipality || '')}" data-user-barangay="${escapeHtml(user.barangay || '')}" aria-label="Edit ${escapeHtml(user.name)}">
                    <i data-lucide="pencil" class="lucide-icon lucide-icon-sm"></i>
                </button>
                ${actions}
            </div>
        </td>
    `;
    tbody.insertBefore(tr, tbody.firstChild);
    bindRowActions(tr);
    replaceUsersIcons();
}

function removeTableRow(userId) {
    const row = document.querySelector(`tr[data-user-id="${userId}"]`);
    if (row) row.remove();
}

function bindRowActions(container) {
    const scope = container || document;
    scope.querySelectorAll('.admin-users-action-view').forEach(btn => {
        btn.addEventListener('click', handleView);
    });
    scope.querySelectorAll('.admin-users-action-edit').forEach(btn => {
        btn.addEventListener('click', handleEdit);
    });
    scope.querySelectorAll('.admin-users-action-delete:not(.is-disabled)').forEach(btn => {
        btn.addEventListener('click', handleDelete);
    });
}

async function handleView(e) {
    const userId = e.currentTarget.dataset.userId;
    if (!userId) return;
    const contentEl = document.getElementById('adminViewUserContent');
    if (!contentEl) return;
    const baseUrl = document.querySelector('[data-admin-users-base]')?.dataset.adminUsersBase
        || (getConfig().routes?.show ? getConfig().routes.show.replace('__ID__', '') : null)
        || (window.location.origin + '/admin/users');
    const showUrl = baseUrl.replace(/\/$/, '') + '/' + userId;
    openModal('adminViewUserModal');
    contentEl.innerHTML = `
        <div class="admin-modal-loading">
            <i data-lucide="loader-2" class="lucide-icon lucide-icon-lg admin-spin" aria-hidden="true"></i>
            <span>Loading…</span>
        </div>
    `;
    replaceUsersIcons();
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 15000);
    function setContent(html) {
        if (contentEl) contentEl.innerHTML = html;
        replaceUsersIcons();
    }
    try {
        const res = await fetch(showUrl, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            signal: controller.signal,
        });
        clearTimeout(timeoutId);
        const contentType = res.headers.get('content-type') || '';
        if (!contentType.includes('application/json')) {
            setContent('<p class="admin-modal-error">Failed to load user details. Please try again.</p>');
            return;
        }
        const data = await res.json();
        if (res.ok) {
            const subEl = document.getElementById('adminViewUserSubtitle');
            if (subEl) subEl.textContent = `Viewing: ${data.name} • ${data.email}`;
            setContent(renderViewContent(data));
        } else {
            setContent('<p class="admin-modal-error">Failed to load user details.</p>');
        }
    } catch (err) {
        clearTimeout(timeoutId);
        if (err.name === 'AbortError') {
            setContent('<p class="admin-modal-error">Request timed out. Please try again.</p>');
        } else {
            setContent('<p class="admin-modal-error">Failed to load user details. Please try again.</p>');
        }
    }
}

function handleEdit(e) {
    const btn = e.currentTarget;
    const userId = btn.dataset.userId;
    const name = btn.dataset.userName || '';
    const email = btn.dataset.userEmail || '';
    const provinceName = (btn.dataset.userProvince || '').trim();
    const municipalityName = (btn.dataset.userMunicipality || '').trim();
    const barangayName = (btn.dataset.userBarangay || '').trim();
    document.getElementById('adminEditUserId').value = userId;
    document.getElementById('adminEditName').value = name;
    document.getElementById('adminEditEmail').value = email;
    document.getElementById('adminEditRole').value = btn.dataset.userRole || 'user';
    document.getElementById('adminEditStatus').value = btn.dataset.userStatus || 'active';
    document.getElementById('adminEditPassword').value = '';
    document.getElementById('adminEditPasswordConfirmation').value = '';
    const subEl = document.getElementById('adminEditUserSubtitle');
    if (subEl) subEl.textContent = `Editing: ${name} • ${email}`;
    clearEditErrors();
    openModal('adminEditUserModal');
    initPsgcForEditModal(provinceName, municipalityName, barangayName);
}

function handleDelete(e) {
    const btn = e.currentTarget;
    document.getElementById('adminDeleteUserName').textContent = btn.dataset.userName || '';
    document.getElementById('adminDeleteUserEmail').textContent = btn.dataset.userEmail || '';
    document.getElementById('adminDeleteUserConfirmBtn').dataset.userId = btn.dataset.userId;
    openModal('adminDeleteUserModal');
}

function clearEditErrors() {
    ['adminEditNameError', 'adminEditEmailError', 'adminEditPasswordError'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.textContent = '';
    });
}

function validateEditForm(form) {
    const name = form.querySelector('#adminEditName').value.trim();
    const email = form.querySelector('#adminEditEmail').value.trim();
    const password = form.querySelector('#adminEditPassword').value;
    const passwordConfirm = form.querySelector('#adminEditPasswordConfirmation').value;
    clearEditErrors();
    let valid = true;
    if (!name) {
        const el = document.getElementById('adminEditNameError');
        if (el) { el.textContent = 'Full name is required.'; valid = false; }
    }
    if (!email) {
        const el = document.getElementById('adminEditEmailError');
        if (el) { el.textContent = 'Email is required.'; valid = false; }
    }
    const oneFilled = password.length > 0 || passwordConfirm.length > 0;
    if (oneFilled) {
        if (password.length < 8) {
            const el = document.getElementById('adminEditPasswordError');
            if (el) { el.textContent = 'Password must be at least 8 characters.'; valid = false; }
        } else if (password !== passwordConfirm) {
            const el = document.getElementById('adminEditPasswordError');
            if (el) { el.textContent = 'Passwords must match.'; valid = false; }
        }
    }
    return valid;
}

function clearAddErrors() {
    ['adminAddNameError', 'adminAddEmailError', 'adminAddPasswordError'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.textContent = '';
    });
}

function showEditErrors(errors) {
    clearEditErrors();
    const map = { name: 'adminEditNameError', email: 'adminEditEmailError', password: 'adminEditPasswordError' };
    Object.entries(errors).forEach(([key, messages]) => {
        const msg = Array.isArray(messages) ? messages[0] : messages;
        const el = document.getElementById(map[key]);
        if (el) el.textContent = msg;
    });
}

function showAddErrors(errors) {
    clearAddErrors();
    Object.entries(errors).forEach(([key, messages]) => {
        const msg = Array.isArray(messages) ? messages[0] : messages;
        const map = { name: 'adminAddNameError', email: 'adminAddEmailError', password: 'adminAddPasswordError' };
        const el = document.getElementById(map[key]);
        if (el) el.textContent = msg;
    });
}

document.addEventListener('DOMContentLoaded', () => {
    replaceUsersIcons();

    window.ADMIN_CURRENT_USER_ID = document.body.dataset.authUserId || null;

    fetchUsers();

    bindModalClose('adminViewUserModal');
    bindModalClose('adminEditUserModal');
    bindModalClose('adminDeleteUserModal');
    bindModalClose('adminAddUserModal');

    bindPsgcCascade('adminEdit');
    bindPsgcCascade('adminAdd');

    document.getElementById('adminAddUserBtn')?.addEventListener('click', async () => {
        document.getElementById('adminAddUserForm')?.reset();
        clearAddErrors();
        openModal('adminAddUserModal');
        await loadPsgcProvinces('adminAdd');
    });

    bindRowActions();

    document.getElementById('adminEditUserForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const form = e.target;
        if (!validateEditForm(form)) return;
        const userId = form.querySelector('#adminEditUserId').value;
        const config = getConfig();
        const url = config.routes?.update?.replace('__ID__', userId);
        if (!url) return;
        const payload = {
            name: form.querySelector('#adminEditName').value.trim(),
            email: form.querySelector('#adminEditEmail').value.trim(),
            role: form.querySelector('#adminEditRole').value,
            status: form.querySelector('#adminEditStatus').value,
            province: form.querySelector('#adminEditProvince').value.trim() || null,
            municipality: form.querySelector('#adminEditMunicipality').value.trim() || null,
            barangay: form.querySelector('#adminEditBarangay').value.trim() || null,
        };
        const password = form.querySelector('#adminEditPassword').value;
        if (password) {
            payload.password = password;
            payload.password_confirmation = form.querySelector('#adminEditPasswordConfirmation').value;
        }
        const submitBtn = document.getElementById('adminEditSaveBtn');
        submitBtn.disabled = true;
        submitBtn.textContent = 'Saving…';
        try {
            const res = await fetch(url, {
                method: 'PUT',
                headers: getCsrfHeaders(),
                body: JSON.stringify(payload),
            });
            const data = await res.json();
            if (res.ok && data.success) {
                closeModal('adminEditUserModal');
                fetchUsers();
                showAlert(data.message || 'User updated successfully.');
            } else {
                if (data.errors) showEditErrors(data.errors);
                else showAlert(data.message || 'An error occurred.', true);
            }
        } catch (err) {
            showAlert('An error occurred. Please try again.', true);
        }
        submitBtn.disabled = false;
        submitBtn.textContent = 'Save';
    });

    document.getElementById('adminDeleteUserConfirmBtn')?.addEventListener('click', async () => {
        const userId = document.getElementById('adminDeleteUserConfirmBtn').dataset.userId;
        const config = getConfig();
        const url = config.routes?.destroy?.replace('__ID__', userId);
        if (!url) return;
        const btn = document.getElementById('adminDeleteUserConfirmBtn');
        btn.disabled = true;
        btn.innerHTML = '<i data-lucide="loader-2" class="lucide-icon lucide-icon-sm admin-spin"></i> Deleting…';
        replaceUsersIcons();
        try {
            const res = await fetch(url, {
                method: 'DELETE',
                headers: getCsrfHeaders(),
            });
            const data = await res.json();
            if (res.ok && data.success) {
                closeModal('adminDeleteUserModal');
                fetchUsers();
                showAlert(data.message || 'User deleted successfully.');
            } else {
                showAlert(data.message || 'Failed to delete user.', true);
            }
        } catch (err) {
            showAlert('An error occurred. Please try again.', true);
        }
        btn.disabled = false;
        btn.textContent = 'Confirm Delete';
    });

    document.getElementById('adminAddUserForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const form = e.target;
        const config = getConfig();
        const url = config.routes?.store;
        if (!url) return;
        const payload = {
            name: form.querySelector('#adminAddName').value,
            email: form.querySelector('#adminAddEmail').value,
            password: form.querySelector('#adminAddPassword').value,
            password_confirmation: form.querySelector('#adminAddPasswordConfirmation').value,
            status: form.querySelector('#adminAddStatus').value,
            province: form.querySelector('#adminAddProvince')?.value || null,
            municipality: form.querySelector('#adminAddMunicipality')?.value || null,
            barangay: form.querySelector('#adminAddBarangay')?.value || null,
        };
        const submitBtn = form.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i data-lucide="loader-2" class="lucide-icon lucide-icon-sm admin-spin"></i> Creating…';
        replaceUsersIcons();
        try {
            const res = await fetch(url, {
                method: 'POST',
                headers: getCsrfHeaders(),
                body: JSON.stringify(payload),
            });
            const data = await res.json();
            if (res.ok && data.success) {
                closeModal('adminAddUserModal');
                fetchUsers();
                showAlert(data.message || 'Admin created successfully.');
            } else {
                if (data.errors) showAddErrors(data.errors);
                else showAlert(data.message || 'An error occurred.', true);
            }
        } catch (err) {
            showAlert('An error occurred. Please try again.', true);
        }
        submitBtn.disabled = false;
        submitBtn.textContent = 'Add Admin';
    });

    document.getElementById('adminUsersPrintBtn')?.addEventListener('click', () => {
        closeExportDropdown();
        const dateEl = document.getElementById('adminUsersPrintDate');
        if (dateEl) dateEl.textContent = new Date().toLocaleString();
        window.print();
    });

    document.getElementById('adminUsersPdfBtn')?.addEventListener('click', async () => {
        closeExportDropdown();
        await exportUsersToPdf();
    });

    function toggleExportDropdown(open) {
        const btn = document.getElementById('adminUsersExportDropdownBtn');
        const menu = document.getElementById('adminUsersExportDropdownMenu');
        if (!btn || !menu) return;
        const isOpen = open ?? !menu.classList.contains('is-open');
        menu.classList.toggle('is-open', isOpen);
        btn.setAttribute('aria-expanded', String(isOpen));
        menu.setAttribute('aria-hidden', String(!isOpen));
    }

    function closeExportDropdown() {
        toggleExportDropdown(false);
    }

    document.getElementById('adminUsersExportDropdownBtn')?.addEventListener('click', (e) => {
        e.stopPropagation();
        toggleExportDropdown();
    });

    document.addEventListener('click', () => closeExportDropdown());
    document.getElementById('adminUsersExportDropdownMenu')?.addEventListener('click', (e) => {
        e.stopPropagation();
    });

    function updateExportLinks() {
        const params = new URLSearchParams(window.location.search);
        params.delete('page');
        params.delete('pageSize');
        const qs = params.toString();
        const base = window.location.origin + '/admin/users/export';
        const excelLink = document.getElementById('adminUsersExcelBtn');
        if (excelLink && excelLink.href !== undefined) excelLink.href = base + '/excel' + (qs ? '?' + qs : '');
    }
    window.adminUsersUpdateExportLinks = updateExportLinks;
    updateExportLinks();
    const filterForm = document.getElementById('adminUsersFilterForm');
    if (filterForm) filterForm.addEventListener('submit', () => { closeExportDropdown(); updateExportLinks(); });

    document.querySelectorAll('.fade-in-element').forEach((el, i) => {
        setTimeout(() => el.classList.add('is-visible'), i * 50);
    });
});
