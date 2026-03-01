/**
 * TOCSEA Shared Export Utilities
 * Reused by: User Calculation History, Saved Equations
 * Provides: buildExportQuery, fetchExportRows, exportPdf, exportExcel, openPrintView, showToast
 */

import { jsPDF } from 'jspdf';
import html2canvas from 'html2canvas';

const CHECK_CIRCLE_SVG = '<svg class="mb-save-toast-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>';
const TOAST_DURATION_MS = 2500;
let toastDismissTimeout = null;

/**
 * Show toast message (same pattern as Calculation History / Saved Equations).
 */
export function showToast(message) {
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
    if (toastDismissTimeout) {
        clearTimeout(toastDismissTimeout);
        toastDismissTimeout = null;
    }
    const safe = String(message).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    toast.innerHTML = CHECK_CIRCLE_SVG + '<span>' + safe + '</span>';
    toast.classList.remove('mb-save-toast-visible');
    requestAnimationFrame(() => {
        requestAnimationFrame(() => {
            toast.classList.add('mb-save-toast-visible');
        });
    });
    toastDismissTimeout = setTimeout(() => {
        toast.classList.remove('mb-save-toast-visible');
        toastDismissTimeout = null;
    }, TOAST_DURATION_MS);
}

/**
 * Escape HTML for export/print.
 */
export function escapeHtmlExport(s) {
    if (s == null) return '';
    return String(s)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

/**
 * Build export API URL with scope and filters.
 * @param {string} baseUrl - Base export URL (e.g. /calculation-history/export or /api/admin/users/export)
 * @param {Object} params - Query params (page, pageSize, search, etc.)
 * @param {'page'|'all'} scope - 'page' = current page only, 'all' = all results
 */
export function buildExportQuery(baseUrl, params, scope) {
    if (!baseUrl) return '';
    const p = new URLSearchParams();
    p.set('scope', scope);
    if (params) {
        Object.entries(params).forEach(([k, v]) => {
            if (v !== '' && v != null) p.set(k, String(v));
        });
    }
    const sep = baseUrl.includes('?') ? '&' : '?';
    return baseUrl + sep + p.toString();
}

/**
 * Fetch export rows from API. Expects { rows: [...] } response.
 */
export async function fetchExportRows(url) {
    if (!url) return [];
    const res = await fetch(url, {
        method: 'GET',
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
    });
    if (!res.ok) throw new Error(res.statusText);
    const data = await res.json();
    return data?.rows || [];
}

/**
 * Get date string for report header.
 */
export function getExportDateString() {
    return new Date().toLocaleString(undefined, {
        year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit',
    });
}

/**
 * Default print styles (repeat header on each page, clean table).
 */
export const PRINT_STYLES =
    'body{margin:0;padding:14mm;font-family:system-ui,sans-serif;font-size:12px;line-height:1.5;color:#111;}' +
    '.export-report-title{margin:0 0 4px;font-size:18px;font-weight:700;}' +
    '.export-report-subtitle{margin-bottom:12px;font-size:11px;color:#444;}' +
    '.export-report-table{width:100%;border-collapse:collapse;margin-top:8px;table-layout:fixed;}' +
    '.export-report-table th,.export-report-table td{padding:8px;text-align:left;border:1px solid #bbb;vertical-align:top;white-space:normal;overflow-wrap:anywhere;word-break:break-word;}' +
    '.export-report-table th{background:#424242;color:#fff;font-weight:700;}' +
    '.export-report-table thead{display:table-header-group;}';

/**
 * Build report table HTML for print/PDF.
 * @param {Array<Object>} rows
 * @param {Array<{key:string,label:string}>} columns
 * @param {string} title
 * @param {string} dateStr
 * @param {function(Object):string[]} getCellValues - (row) => [cell1, cell2, ...]
 */
export function buildReportTableHtml(rows, columns, title, dateStr, getCellValues) {
    const theadCells = columns.map(c => `<th>${escapeHtmlExport(c.label)}</th>`).join('');
    const thead = `<thead><tr>${theadCells}</tr></thead>`;
    const tbodyRows = rows
        .map((r) => {
            const vals = getCellValues(r);
            const cells = vals.map(v => `<td>${escapeHtmlExport(v)}</td>`).join('');
            return `<tr>${cells}</tr>`;
        })
        .join('');
    return (
        '<h1 class="export-report-title">' + escapeHtmlExport(title) + '</h1>' +
        '<p class="export-report-subtitle">TOCSEA &middot; ' + escapeHtmlExport(dateStr) + '</p>' +
        '<table class="export-report-table">' + thead + '<tbody>' + tbodyRows + '</tbody></table>'
    );
}

/**
 * Export rows to PDF (A4 portrait; uses html2canvas).
 */
export async function exportPdf(rows, options) {
    const {
        reportTitle,
        columns,
        getCellValues,
        filename = 'report.pdf',
    } = options;

    if (rows.length === 0) return false;

    const dateStr = getExportDateString();
    const html = buildReportTableHtml(rows, columns, reportTitle, dateStr, getCellValues);

    const wrap = document.createElement('div');
    wrap.className = 'ch-pdf-export-wrap';
    wrap.innerHTML = html;
    wrap.style.width = '794px';
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
        const colCount = columns.length;
        const doc = new jsPDF({ orientation: colCount > 5 ? 'l' : 'p', unit: 'mm', format: 'a4' });
        const pageW = doc.internal.pageSize.getWidth();
        const pageH = doc.internal.pageSize.getHeight();
        let w = imgW;
        let h = imgH;
        if (h > pageH) { const ratio = pageH / h; w *= ratio; h = pageH; }
        if (w > pageW) { const ratio = pageW / w; w = pageW; h *= ratio; }
        doc.addImage(canvas.toDataURL('image/png'), 'PNG', 0, 0, w, h);
        doc.save(filename);
        return true;
    } catch (e) {
        console.error('PDF export failed:', e);
        if (wrap.parentNode) wrap.remove();
        return false;
    }
}

/**
 * Export rows to Excel (styled header, borders).
 */
export async function exportExcel(rows, options) {
    const {
        columns,
        sheetName = 'Sheet1',
        filename = 'export.xlsx',
        getCellValues,
    } = options;

    if (rows.length === 0) return false;

    const XLSX = await import('xlsx');
    const headers = columns.map(c => c.label);
    const data = [
        headers,
        ...rows.map(r => getCellValues(r)),
    ];
    const ws = XLSX.utils.aoa_to_sheet(data);
    ws['!cols'] = columns.map(() => ({ wch: 18 }));
    const range = XLSX.utils.decode_range(ws['!ref'] || 'A1');
    for (let c = range.s.c; c <= range.e.c; c++) {
        const addr = XLSX.utils.encode_cell({ r: 0, c });
        if (ws[addr]) ws[addr].s = { font: { bold: true }, fill: { fgColor: { rgb: 'FFE0E0E0' } } };
    }
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, sheetName);
    XLSX.writeFile(wb, filename);
    return true;
}

/**
 * Open print view in iframe (auto-trigger print dialog).
 */
export function openPrintView(rows, options) {
    const {
        reportTitle,
        columns,
        getCellValues,
    } = options;

    if (rows.length === 0) return false;

    const dateStr = getExportDateString();
    const html = buildReportTableHtml(rows, columns, reportTitle, dateStr, getCellValues);

    const fullHtml =
        '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>' + escapeHtmlExport(reportTitle) + ' - TOCSEA</title>' +
        '<style>' + PRINT_STYLES + '</style></head><body>' + html + '</body></html>';

    const iframe = document.createElement('iframe');
    iframe.setAttribute('style', 'position:absolute;width:0;height:0;border:none;');
    document.body.appendChild(iframe);
    const doc = iframe.contentWindow?.document;
    if (!doc) {
        iframe.remove();
        return false;
    }
    doc.open();
    doc.write(fullHtml);
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
    } else {
        iframe.remove();
    }
    return true;
}
