/**
 * TOCSEA Admin - Reusable Pagination System
 * usePagination({ page, pageSize, totalItems }) → { totalPages, startIndex, endIndex, canPrev, canNext, pageButtons }
 * renderAdminPagination(container, pagination, options)
 */

import { ChevronLeft, ChevronRight } from 'lucide';
import { createIcons } from 'lucide';

const PAGINATION_ICONS = { ChevronLeft, ChevronRight };

const PAGE_SIZE_OPTIONS = [10, 25, 50, 100];

/**
 * Compute pagination metadata for UI.
 * @param {Object} params
 * @param {number} params.page - Current page (1-based)
 * @param {number} params.pageSize - Items per page
 * @param {number} params.totalItems - Total count
 * @returns {Object} { totalPages, startIndex, endIndex, canPrev, canNext, pageButtons, rangeText }
 */
function usePagination({ page = 1, pageSize = 25, totalItems = 0 }) {
    const totalPages = Math.max(1, Math.ceil(totalItems / pageSize) || 1);
    const currentPage = Math.max(1, Math.min(page, totalPages));
    const startIndex = totalItems === 0 ? 0 : (currentPage - 1) * pageSize + 1;
    const endIndex = Math.min(currentPage * pageSize, totalItems);
    const canPrev = currentPage > 1;
    const canNext = currentPage < totalPages;

    const pageButtons = [];
    const showEllipsis = totalPages > 7;
    if (!showEllipsis) {
        for (let i = 1; i <= totalPages; i++) {
            pageButtons.push({ page: i, label: String(i), isEllipsis: false });
        }
    } else {
        pageButtons.push({ page: 1, label: '1', isEllipsis: false });
        let left = Math.max(2, currentPage - 1);
        let right = Math.min(totalPages - 1, currentPage + 1);
        if (currentPage <= 3) right = 4;
        if (currentPage >= totalPages - 2) left = totalPages - 3;
        if (left > 2) pageButtons.push({ page: null, label: '…', isEllipsis: true });
        for (let i = left; i <= right; i++) {
            if (i > 1 && i < totalPages) {
                pageButtons.push({ page: i, label: String(i), isEllipsis: false });
            }
        }
        if (right < totalPages - 1) pageButtons.push({ page: null, label: '…', isEllipsis: true });
        if (totalPages > 1) pageButtons.push({ page: totalPages, label: String(totalPages), isEllipsis: false });
    }

    const rangeText = totalItems === 0
        ? 'No results'
        : `Showing ${startIndex}–${endIndex} of ${totalItems.toLocaleString()}`;

    return {
        page: currentPage,
        pageSize,
        totalItems,
        totalPages,
        startIndex,
        endIndex,
        canPrev,
        canNext,
        pageButtons,
        rangeText,
    };
}

/**
 * Render pagination bar into a container.
 * @param {HTMLElement} container - Container element
 * @param {Object} meta - Result from usePagination
 * @param {Object} options
 * @param {Function} options.onPageChange - (page) => void
 * @param {Function} options.onPageSizeChange - (pageSize) => void
 * @param {boolean} options.loading - Show loading state
 * @param {string} options.containerId - ID for the pagination container (for accessibility)
 */
function renderAdminPagination(container, meta, options = {}) {
    const { onPageChange, onPageSizeChange, loading = false, containerId = 'admin-pagination' } = options;

    const disabledClass = loading ? ' is-disabled' : '';
    const prevDisabled = meta.canPrev && !loading ? '' : ' disabled aria-disabled="true"';
    const nextDisabled = meta.canNext && !loading ? '' : ' disabled aria-disabled="true"';

    let pageButtonsHtml = '';
    meta.pageButtons.forEach(({ page, label, isEllipsis }) => {
        if (isEllipsis) {
            pageButtonsHtml += `<span class="admin-pagination-ellipsis" aria-hidden="true">${label}</span>`;
        } else {
            const isActive = page === meta.page ? ' is-active' : '';
            const disabled = loading ? ' disabled aria-disabled="true"' : '';
            pageButtonsHtml += `<button type="button" class="admin-pagination-btn admin-pagination-page${isActive}" data-page="${page}"${disabled} aria-label="Page ${page}" aria-current="${page === meta.page ? 'page' : 'false'}">${label}</button>`;
        }
    });

    let pageSizeHtml = '';
    PAGE_SIZE_OPTIONS.forEach((size) => {
        const sel = size === meta.pageSize ? ' selected' : '';
        pageSizeHtml += `<option value="${size}"${sel}>${size}</option>`;
    });

    container.innerHTML = `
        <div id="${containerId}" class="admin-pagination${disabledClass}" role="navigation" aria-label="Pagination">
            <div class="admin-pagination-range">${meta.rangeText}</div>
            <div class="admin-pagination-controls">
                <div class="admin-pagination-pages">
                    <button type="button" class="admin-pagination-btn admin-pagination-prev" data-page="${meta.page - 1}"${prevDisabled} aria-label="Previous page">
                        <i data-lucide="chevron-left" class="lucide-icon lucide-icon-sm" aria-hidden="true"></i>
                    </button>
                    <span class="admin-pagination-page-list" role="group" aria-label="Page numbers">
                        ${pageButtonsHtml}
                    </span>
                    <button type="button" class="admin-pagination-btn admin-pagination-next" data-page="${meta.page + 1}"${nextDisabled} aria-label="Next page">
                        <i data-lucide="chevron-right" class="lucide-icon lucide-icon-sm" aria-hidden="true"></i>
                    </button>
                </div>
                <div class="admin-pagination-size">
                    <label for="${containerId}-size" class="admin-pagination-size-label">Rows per page</label>
                    <select id="${containerId}-size" class="admin-pagination-size-select"${loading ? ' disabled' : ''} aria-label="Rows per page">
                        ${pageSizeHtml}
                    </select>
                </div>
            </div>
        </div>
    `;

    createIcons({ icons: PAGINATION_ICONS, attrs: { 'stroke-width': 2 }, root: container });

    if (onPageChange) {
        container.querySelectorAll('.admin-pagination-page:not(.is-active), .admin-pagination-prev, .admin-pagination-next').forEach((btn) => {
            if (btn.disabled) return;
            btn.addEventListener('click', () => {
                const page = parseInt(btn.dataset.page, 10);
                if (!Number.isNaN(page) && page >= 1) onPageChange(page);
            });
        });
    }

    if (onPageSizeChange) {
        const select = container.querySelector('.admin-pagination-size-select');
        if (select) {
            select.addEventListener('change', () => {
                const size = parseInt(select.value, 10);
                if (!Number.isNaN(size)) onPageSizeChange(size);
            });
        }
    }

    container.querySelector('.admin-pagination')?.addEventListener('keydown', (e) => {
        if (e.target.closest('.admin-pagination-btn') && (e.key === 'Enter' || e.key === ' ')) {
            e.preventDefault();
            e.target.click?.();
        }
    });
}

export { usePagination, renderAdminPagination, PAGE_SIZE_OPTIONS };
