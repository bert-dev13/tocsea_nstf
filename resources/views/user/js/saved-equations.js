/**
 * Saved Equations standalone page - table, pagination, Edit/Delete modals.
 * No regression logic. Uses RESTful SavedEquationController API.
 * Export: Excel, PDF (html2canvas + jsPDF from cloned ReportPrintLayout), Print (same layout in iframe).
 * xlsx remains dynamic for Excel export.
 */

import { jsPDF } from 'jspdf';
import html2canvas from 'html2canvas';

const MB_SAVED_EQ_TOAST = 'mbSavedEquationsToast';

if (typeof sessionStorage !== 'undefined' && (sessionStorage.getItem(MB_SAVED_EQ_TOAST) === 'updated' || sessionStorage.getItem(MB_SAVED_EQ_TOAST) === 'deleted')) {
    if (typeof history !== 'undefined' && history.scrollRestoration) {
        history.scrollRestoration = 'manual';
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const savedEquationsEmpty = document.getElementById('savedEquationsEmpty');
    const savedEquationsTableWrap = document.getElementById('savedEquationsTableWrap');
    const savedEquationsTableBody = document.getElementById('savedEquationsTableBody');
    const savedEquationsLoading = document.getElementById('savedEquationsLoading');
    const savedEquationsPagination = document.getElementById('savedEquationsPagination');

    let currentSavedPage = 1;
    let deleteEquationId = null;
    /** Current page data from API (unchanged by filters) */
    let currentPageEquations = [];
    let currentPagination = null;
    /** Filter state (applied client-side to current page only) */
    let filterSearch = '';
    let filterDateFrom = '';
    let filterDateTo = '';
    let filterSort = 'newest';

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
        const safe = String(message).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        toast.innerHTML = CHECK_CIRCLE_SVG + '<span>' + safe + '</span>';
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

    try {
        const toastFlag = sessionStorage.getItem(MB_SAVED_EQ_TOAST);
        if (toastFlag === 'updated' || toastFlag === 'deleted') {
            sessionStorage.removeItem(MB_SAVED_EQ_TOAST);
            if (typeof history !== 'undefined' && history.scrollRestoration) {
                history.scrollRestoration = 'manual';
            }
            window.scrollTo(0, 0);
            document.documentElement.scrollTop = 0;
            document.body.scrollTop = 0;
            requestAnimationFrame(() => {
                showToast(toastFlag === 'updated' ? 'Equation updated successfully.' : 'Equation deleted successfully.');
            });
        }
    } catch (_) { /* ignore */ }

    function escapeHtml(s) {
        const d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    function escapeHtmlAttr(s) {
        if (s == null) return '';
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    /* Inline SVGs so icons are always visible (no dependency on Lucide createIcons) */
    const iconPencil = '<svg class="mb-saved-action-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16" aria-hidden="true"><path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"/><path d="m15 5 4 4"/></svg>';
    const iconTrash2 = '<svg class="mb-saved-action-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16" aria-hidden="true"><path d="M10 11v6"/><path d="M14 11v6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>';
    const iconMoreVertical = '<svg class="mb-saved-action-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16" aria-hidden="true"><circle cx="12" cy="12" r="1"/><circle cx="12" cy="5" r="1"/><circle cx="12" cy="19" r="1"/></svg>';

    function getSavedEquationsUrls() {
        const page = document.getElementById('savedEquationsPage');
        const base = page?.dataset?.savedEquationsBaseUrl || '/saved-equations';
        return {
            index: page?.dataset?.savedEquationsIndexUrl || base,
            base,
        };
    }

    function formatSavedDate(iso) {
        if (!iso) return '—';
        const d = new Date(iso);
        return isNaN(d.getTime()) ? '—' : d.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
    }

    /** Parse ISO date to YYYY-MM-DD for comparison with date inputs */
    function toDateOnly(iso) {
        if (!iso) return '';
        const d = new Date(iso);
        return isNaN(d.getTime()) ? '' : d.toISOString().slice(0, 10);
    }

    function getFilteredAndSortedList() {
        let list = [...currentPageEquations];
        const q = (filterSearch || '').trim().toLowerCase();
        if (q) {
            list = list.filter((eq) => {
                const name = (eq.equation_name || '').toLowerCase();
                const formula = (eq.formula || '').replace(/\s+/g, ' ').toLowerCase();
                return name.includes(q) || formula.includes(q);
            });
        }
        if (filterDateFrom) {
            list = list.filter((eq) => toDateOnly(eq.created_at) >= filterDateFrom);
        }
        if (filterDateTo) {
            list = list.filter((eq) => toDateOnly(eq.created_at) <= filterDateTo);
        }
        const sort = filterSort || 'newest';
        list.sort((a, b) => {
            if (sort === 'newest') return new Date(b.created_at) - new Date(a.created_at);
            if (sort === 'oldest') return new Date(a.created_at) - new Date(b.created_at);
            if (sort === 'updated') return new Date(b.updated_at || 0) - new Date(a.updated_at || 0);
            if (sort === 'name_az') return (a.equation_name || '').localeCompare(b.equation_name || '', undefined, { sensitivity: 'base' });
            if (sort === 'name_za') return (b.equation_name || '').localeCompare(a.equation_name || '', undefined, { sensitivity: 'base' });
            return 0;
        });
        return list;
    }

    function buildEquationRow(eq) {
        const formulaMaxLen = 80;
        const formulaText = (eq.formula || '').replace(/\s+/g, ' ').trim();
        const isLong = formulaText.length > formulaMaxLen;
        const formulaShort = isLong ? formulaText.slice(0, formulaMaxLen) + '…' : formulaText;
        const createdStr = formatSavedDate(eq.created_at);
        const updatedStr = formatSavedDate(eq.updated_at);
        const formulaHtml = isLong
            ? `<span class="mb-saved-formula-text" data-full="${escapeHtmlAttr(formulaText)}">${escapeHtml(formulaShort)}</span><button type="button" class="mb-saved-formula-toggle" aria-label="Show full formula">Show more</button>`
            : escapeHtml(formulaText);
        const editData = `data-id="${escapeHtml(String(eq.id))}" data-name="${escapeHtml(eq.equation_name)}" data-formula="${escapeHtmlAttr(eq.formula || '')}"`;
        const deleteData = `data-id="${escapeHtml(String(eq.id))}" data-name="${escapeHtml(eq.equation_name)}"`;
        return `
            <td class="mb-saved-name-cell">${escapeHtml(eq.equation_name)}</td>
            <td class="mb-saved-formula-cell" title="${escapeHtml(formulaText)}">${formulaHtml}</td>
            <td class="mb-saved-date-cell" data-label="Created: ">${escapeHtml(createdStr)}</td>
            <td class="mb-saved-date-cell" data-label="Updated: ">${escapeHtml(updatedStr)}</td>
            <td class="mb-saved-actions-cell">
                <div class="mb-saved-actions-inner">
                    <button type="button" class="mb-saved-action-btn mb-saved-action-edit mb-saved-action-icon-only" ${editData} aria-label="Edit equation">${iconPencil}</button>
                    <button type="button" class="mb-saved-action-btn mb-saved-action-delete mb-saved-action-icon-only" ${deleteData} aria-label="Delete equation">${iconTrash2}</button>
                </div>
                <div class="mb-saved-actions-dropdown">
                    <button type="button" class="mb-saved-actions-dropdown-btn" aria-label="Actions" aria-expanded="false" aria-haspopup="true">${iconMoreVertical}</button>
                    <div class="mb-saved-actions-dropdown-menu" role="menu" hidden>
                        <button type="button" role="menuitem" class="mb-saved-action-btn mb-saved-action-edit" ${editData} aria-label="Edit equation">${iconPencil} Edit</button>
                        <button type="button" role="menuitem" class="mb-saved-action-btn mb-saved-action-delete" ${deleteData} aria-label="Delete equation">${iconTrash2} Delete</button>
                    </div>
                </div>
            </td>
        `;
    }

    function renderTableRows(list) {
        if (!savedEquationsTableBody) return;
        savedEquationsTableBody.innerHTML = list.map((eq) => `<tr>${buildEquationRow(eq)}</tr>`).join('');
        bindSavedTableActions();
    }

    function applyFiltersAndRender() {
        const list = getFilteredAndSortedList();
        renderTableRows(list);
        const hasAny = currentPageEquations.length > 0;
        const hasFiltered = list.length > 0;
        if (savedEquationsEmpty) {
            savedEquationsEmpty.textContent = !hasAny
                ? 'No saved equations yet. Run a regression on the Model Builder page and click Save Equation to store one.'
                : 'No equations match your filters.';
            savedEquationsEmpty.hidden = hasFiltered;
        }
        if (savedEquationsTableWrap) savedEquationsTableWrap.hidden = !hasFiltered;
        renderSavedEquationsPagination(currentPagination);
        updateExportButtonState(hasFiltered);
    }

    /** Rows for export/print: current filtered & sorted list (no Actions column) */
    function getExportRows() {
        const list = getFilteredAndSortedList();
        return list.map((eq) => ({
            name: eq.equation_name || '',
            formula: (eq.formula || '').replace(/\s+/g, ' ').trim(),
            dateCreated: formatSavedDate(eq.created_at),
            dateUpdated: formatSavedDate(eq.updated_at),
        }));
    }

    function getExportDateString() {
        return new Date().toLocaleString(undefined, {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    }

    let exportInProgress = false;

    function setExportLoading(loading) {
        exportInProgress = loading;
        const btn = document.getElementById('savedEquationsExportBtn');
        const menu = document.getElementById('savedEquationsExportMenu');
        if (btn) btn.disabled = loading;
        if (menu) {
            menu.querySelectorAll('.mb-se-export-item').forEach((item) => {
                item.disabled = loading;
            });
        }
    }

    function updateExportButtonState(hasRecords) {
        const btn = document.getElementById('savedEquationsExportBtn');
        if (!btn) return;
        if (exportInProgress) return;
        btn.disabled = !hasRecords;
    }

    async function exportToExcel() {
        const rows = getExportRows();
        if (rows.length === 0) return;
        setExportLoading(true);
        try {
            const XLSX = await import('xlsx');
            const headers = ['Equation Name', 'Formula', 'Date Created', 'Last Updated'];
            const data = [headers, ...rows.map((r) => [r.name, r.formula, r.dateCreated, r.dateUpdated])];
            const ws = XLSX.utils.aoa_to_sheet(data);
            const colWidths = [{ wch: 24 }, { wch: 60 }, { wch: 14 }, { wch: 14 }];
            ws['!cols'] = colWidths;
            const range = XLSX.utils.decode_range(ws['!ref'] || 'A1');
            for (let c = range.s.c; c <= range.e.c; c++) {
                const addr = XLSX.utils.encode_cell({ r: 0, c });
                if (!ws[addr]) continue;
                ws[addr].s = { font: { bold: true } };
            }
            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, 'Saved Equations');
            XLSX.writeFile(wb, 'tocsea-saved-equations-' + new Date().toISOString().slice(0, 10) + '.xlsx');
        } catch (e) {
            console.error('Excel export error', e);
            showToast('Export failed. Please try again.');
        } finally {
            setExportLoading(false);
            updateExportButtonState(getExportRows().length > 0);
        }
    }

    /** Inline styles for Print dialog iframe only (must match .pdf-export / saved-equations.css). */
    const PRINT_REPORT_INLINE_STYLES =
        'body{margin:0;padding:14mm;font-family:system-ui,sans-serif;font-size:12px;line-height:1.5;color:#111;}' +
        '.se-report-title{margin:0 0 4px;font-size:18px;font-weight:700;}' +
        '.se-report-subtitle{margin-bottom:12px;font-size:11px;color:#444;}' +
        '.se-report-table{width:100%;border-collapse:collapse;margin-top:8px;table-layout:fixed;}' +
        '.se-report-table th,.se-report-table td{padding:8px;text-align:left;border:1px solid #bbb;vertical-align:top;white-space:normal;overflow-wrap:anywhere;word-break:break-word;letter-spacing:normal;word-spacing:normal;}' +
        '.se-report-table th{background:#424242;color:#fff;font-weight:700;}';

    /**
     * Single source of truth: ReportPrintLayout — the exact table markup used for printing (and cloned for PDF).
     * Returns a DOM element (div.se-report-print-layout) with title, subtitle, and table. No Actions column.
     */
    function createReportPrintLayout(rows, dateStr) {
        const wrap = document.createElement('div');
        wrap.className = 'se-report-print-layout';
        const tableRows = rows
            .map(
                (r) =>
                    '<tr><td>' + escapeHtml(r.name) + '</td><td>' + escapeHtml(r.formula) + '</td><td>' + escapeHtml(r.dateCreated) + '</td><td>' + escapeHtml(r.dateUpdated) + '</td></tr>'
            )
            .join('');
        const escapedDate = escapeHtml(dateStr);
        wrap.innerHTML =
            '<h1 class="se-report-title">Saved Equations Report</h1>' +
            '<p class="se-report-subtitle">TOCSEA &middot; ' + escapedDate + '</p>' +
            '<table class="se-report-table">' +
            '<thead><tr><th>Equation Name</th><th>Formula</th><th>Date Created</th><th>Last Updated</th></tr></thead><tbody>' +
            tableRows +
            '</tbody></table>';
        return wrap;
    }

    /**
     * Full HTML document for Print dialog iframe (uses same structure as ReportPrintLayout).
     */
    function buildSavedEquationsReportPrintHTML(rows, dateStr) {
        const layout = createReportPrintLayout(rows, dateStr);
        const bodyHtml = layout.innerHTML;
        return (
            '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Saved Equations Report - TOCSEA</title>' +
            '<style>' + PRINT_REPORT_INLINE_STYLES + '</style></head><body>' +
            bodyHtml +
            '</body></html>'
        );
    }

    /**
     * PDF export: clone ReportPrintLayout into a .pdf-export container, apply print-equivalent styles,
     * capture with html2canvas, then add to jsPDF. Result matches Print Preview (no overlapping Formula column).
     */
    async function exportToPdf() {
        const rows = getExportRows();
        if (rows.length === 0) return;
        setExportLoading(true);
        const dateStr = getExportDateString();
        const layout = createReportPrintLayout(rows, dateStr);
        const clone = layout.cloneNode(true);
        const exportContainer = document.createElement('div');
        exportContainer.className = 'pdf-export';
        /* A4 portrait width in px at 96dpi: 210mm ≈ 793.7px; use 794 so content fits one page width */
        const a4WidthPx = 794;
        exportContainer.style.width = a4WidthPx + 'px';
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
            });
            exportContainer.remove();
            const imgW = (canvas.width / scale) * 0.264583333; /* px to mm at 96dpi */
            const imgH = (canvas.height / scale) * 0.264583333;
            const doc = new jsPDF({ orientation: 'p', unit: 'mm', format: 'a4' });
            const pageW = doc.internal.pageSize.getWidth();
            const pageH = doc.internal.pageSize.getHeight();
            /* Fit content to one page; if very long, scale down to fit height */
            let w = imgW;
            let h = imgH;
            if (h > pageH) {
                const ratio = pageH / h;
                w = w * ratio;
                h = pageH;
            }
            if (w > pageW) {
                const ratio = pageW / w;
                w = pageW;
                h = h * ratio;
            }
            doc.addImage(canvas.toDataURL('image/png'), 'PNG', 0, 0, w, h);
            doc.save('Saved_Equations_Report.pdf');
        } catch (e) {
            console.error('PDF export failed:', e);
            if (exportContainer.parentNode) exportContainer.remove();
            showToast('PDF export failed. Please try again.');
        } finally {
            setExportLoading(false);
            updateExportButtonState(getExportRows().length > 0);
        }
    }

    /**
     * Opens print dialog with SavedEquationsReportPrint content (Print action only).
     */
    function openPrintReport() {
        const rows = getExportRows();
        if (rows.length === 0) return;
        setExportLoading(true);
        try {
            const dateStr = getExportDateString();
            const printContent = buildSavedEquationsReportPrintHTML(rows, dateStr);
            const iframe = document.createElement('iframe');
            iframe.setAttribute('style', 'position:absolute;width:0;height:0;border:none;');
            document.body.appendChild(iframe);
            const iframeDoc = iframe.contentWindow?.document;
            if (!iframeDoc) {
                iframe.remove();
                showToast('Print failed. Please try again.');
                return;
            }
            iframeDoc.open();
            iframeDoc.write(printContent);
            iframeDoc.close();
            const iframeWin = iframe.contentWindow;
            if (iframeWin) {
                iframeWin.focus();
                requestAnimationFrame(() => {
                    setTimeout(() => {
                        iframeWin.print();
                        const onAfterPrint = () => {
                            iframe.remove();
                            iframeWin.removeEventListener('afterprint', onAfterPrint);
                        };
                        iframeWin.addEventListener('afterprint', onAfterPrint);
                    }, 150);
                });
            } else {
                iframe.remove();
                showToast('Print failed. Please try again.');
            }
        } catch (e) {
            console.error('Print error', e);
            showToast('Print failed. Please try again.');
        } finally {
            setExportLoading(false);
            updateExportButtonState(getExportRows().length > 0);
        }
    }

    function printReport() {
        openPrintReport();
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
        if (savedEquationsLoading) savedEquationsLoading.hidden = false;
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
            currentPageEquations = list;
            currentPagination = pagination;
            applyFiltersAndRender();
        } catch (_) {
            if (savedEquationsEmpty) {
                savedEquationsEmpty.textContent = 'Unable to load saved equations.';
                savedEquationsEmpty.hidden = false;
            }
            if (savedEquationsTableWrap) savedEquationsTableWrap.hidden = true;
            if (savedEquationsPagination) {
                savedEquationsPagination.hidden = true;
                savedEquationsPagination.innerHTML = '';
            }
        } finally {
            if (savedEquationsLoading) savedEquationsLoading.hidden = true;
        }
    }

    function bindSavedTableActions() {
        if (!savedEquationsTableBody) return;
        /* Formula Show more / Show less */
        savedEquationsTableBody.querySelectorAll('.mb-saved-formula-toggle').forEach((btn) => {
            btn.replaceWith(btn.cloneNode(true));
        });
        savedEquationsTableBody.querySelectorAll('.mb-saved-formula-toggle').forEach((btn) => {
            btn.addEventListener('click', () => {
                const wrap = btn.previousElementSibling;
                if (!wrap || !wrap.classList) return;
                const isExpanded = wrap.classList.toggle('is-expanded');
                if (isExpanded) {
                    const full = wrap.getAttribute('data-full') || '';
                    wrap.textContent = full;
                    btn.textContent = 'Show less';
                    btn.setAttribute('aria-label', 'Show less formula');
                } else {
                    const full = wrap.getAttribute('data-full') || '';
                    const short = full.length > 80 ? full.slice(0, 80) + '…' : full;
                    wrap.textContent = short;
                    btn.textContent = 'Show more';
                    btn.setAttribute('aria-label', 'Show full formula');
                }
            });
        });
        /* Mobile actions dropdown */
        savedEquationsTableBody.querySelectorAll('.mb-saved-actions-dropdown').forEach((dd) => {
            const btn = dd.querySelector('.mb-saved-actions-dropdown-btn');
            const menu = dd.querySelector('.mb-saved-actions-dropdown-menu');
            if (!btn || !menu) return;
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const isOpen = menu.hidden === false;
                document.querySelectorAll('.mb-saved-actions-dropdown-menu.is-open').forEach((m) => {
                    m.classList.remove('is-open');
                    m.hidden = true;
                });
                if (!isOpen) {
                    menu.classList.add('is-open');
                    menu.hidden = false;
                    btn.setAttribute('aria-expanded', 'true');
                } else {
                    btn.setAttribute('aria-expanded', 'false');
                }
            });
            menu.querySelectorAll('.mb-saved-action-btn').forEach((actionBtn) => {
                actionBtn.addEventListener('click', () => {
                    menu.classList.remove('is-open');
                    menu.hidden = true;
                    btn.setAttribute('aria-expanded', 'false');
                });
            });
        });
    }

    function closeAllActionsDropdowns() {
        const open = document.querySelector('.mb-saved-actions-dropdown-menu.is-open');
        if (open) {
            open.classList.remove('is-open');
            open.hidden = true;
            const btn = open.closest('.mb-saved-actions-dropdown')?.querySelector('.mb-saved-actions-dropdown-btn');
            if (btn) btn.setAttribute('aria-expanded', 'false');
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

    savedEquationsTableBody?.addEventListener('click', (e) => {
        const editBtn = e.target.closest('.mb-saved-action-edit');
        const deleteBtn = e.target.closest('.mb-saved-action-delete');
        if (editBtn) {
            openEditEquationModal(editBtn.dataset.id, editBtn.dataset.name ?? '', editBtn.dataset.formula ?? '');
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
                try {
                    sessionStorage.setItem(MB_SAVED_EQ_TOAST, 'updated');
                } catch (_) { /* ignore */ }
                window.location.reload();
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
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf || '' },
            });
            const data = await res.json().catch(() => ({}));
            if (res.ok && data?.success) {
                closeDeleteEquationModal();
                try {
                    sessionStorage.setItem(MB_SAVED_EQ_TOAST, 'deleted');
                } catch (_) { /* ignore */ }
                window.location.reload();
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

    /* Filter bar: same behavior as Calculation History — instant, client-side, current page only */
    const seSearchEl = document.getElementById('seSearch');
    const seDateFromEl = document.getElementById('seDateFrom');
    const seDateToEl = document.getElementById('seDateTo');
    const seSortEl = document.getElementById('seSort');
    const seClearFiltersEl = document.getElementById('seClearFilters');

    function syncFilterStateFromInputs() {
        filterSearch = (seSearchEl?.value || '').trim();
        filterDateFrom = seDateFromEl?.value || '';
        filterDateTo = seDateToEl?.value || '';
        filterSort = seSortEl?.value || 'newest';
    }

    function clearFilterInputs() {
        if (seSearchEl) seSearchEl.value = '';
        if (seDateFromEl) seDateFromEl.value = '';
        if (seDateToEl) seDateToEl.value = '';
        if (seSortEl) seSortEl.value = 'newest';
        filterSearch = '';
        filterDateFrom = '';
        filterDateTo = '';
        filterSort = 'newest';
    }

    seSearchEl?.addEventListener('input', () => {
        syncFilterStateFromInputs();
        applyFiltersAndRender();
    });
    seDateFromEl?.addEventListener('change', () => {
        syncFilterStateFromInputs();
        applyFiltersAndRender();
    });
    seDateToEl?.addEventListener('change', () => {
        syncFilterStateFromInputs();
        applyFiltersAndRender();
    });
    seSortEl?.addEventListener('change', () => {
        syncFilterStateFromInputs();
        applyFiltersAndRender();
    });
    seClearFiltersEl?.addEventListener('click', () => {
        clearFilterInputs();
        applyFiltersAndRender();
    });

    document.getElementById('seApplyFilters')?.addEventListener('click', () => {
        syncFilterStateFromInputs();
        applyFiltersAndRender();
    });

    loadSavedEquationsTable();

    /* Export dropdown */
    const exportWrap = document.querySelector('.mb-se-export-wrap');
    const exportBtn = document.getElementById('savedEquationsExportBtn');
    const exportMenu = document.getElementById('savedEquationsExportMenu');
    if (exportBtn && exportMenu) {
        exportBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            if (exportBtn.disabled) return;
            const wasOpen = !exportMenu.hidden;
            exportMenu.hidden = wasOpen;
            exportWrap?.classList.toggle('is-open', !wasOpen);
            exportBtn.setAttribute('aria-expanded', String(!wasOpen));
        });
        exportMenu.querySelectorAll('.mb-se-export-item').forEach((item) => {
            item.addEventListener('click', () => {
                const action = item.getAttribute('data-export');
                exportMenu.hidden = true;
                exportWrap?.classList.remove('is-open');
                exportBtn?.setAttribute('aria-expanded', 'false');
                if (action === 'excel') exportToExcel();
                else if (action === 'pdf') exportToPdf();
                else if (action === 'print') printReport();
            });
        });
    }
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.mb-saved-actions-dropdown')) closeAllActionsDropdowns();
        if (exportMenu && !exportMenu.hidden && !e.target.closest('.mb-se-export-wrap')) {
            exportMenu.hidden = true;
            exportWrap?.classList.remove('is-open');
            exportBtn?.setAttribute('aria-expanded', 'false');
        }
    });

    if (typeof lucide !== 'undefined') lucide.createIcons();
});
