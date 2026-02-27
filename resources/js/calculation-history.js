/**
 * TOCSEA Calculation History - Re-run, delete
 */

import { createIcons, History, Calculator, RotateCcw, Trash2, Inbox, Eye } from 'lucide';

const page = document.getElementById('calculationHistoryPage');
if (!page) throw new Error('Calculation History page not found');

// Base URL for calculation history endpoints (delete, show, rerun)
const historyBaseUrl = page.dataset.historyBaseUrl || page.dataset.deleteUrl || '';
const deleteBaseUrl = historyBaseUrl;
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

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
            removeRow(id);
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
const detailsDownload = document.getElementById('chDetailsDownload');
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

detailsDownload?.addEventListener('click', () => {
    if (!currentDetailsId || !historyBaseUrl) return;
    const url = `${historyBaseUrl}/${encodeURIComponent(currentDetailsId)}?format=pdf`;
    window.open(url, '_blank', 'noopener');
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

createIcons({ icons: { History, Calculator, RotateCcw, Trash2, Inbox, Eye }, nameAttr: 'data-lucide' });
