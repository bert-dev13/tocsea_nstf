/**
 * Saved Equations standalone page - table, pagination, Edit/Delete modals.
 * No regression logic. Uses RESTful SavedEquationController API.
 */

document.addEventListener('DOMContentLoaded', () => {
    const savedEquationsEmpty = document.getElementById('savedEquationsEmpty');
    const savedEquationsTableWrap = document.getElementById('savedEquationsTableWrap');
    const savedEquationsTableBody = document.getElementById('savedEquationsTableBody');
    const savedEquationsLoading = document.getElementById('savedEquationsLoading');
    const savedEquationsPagination = document.getElementById('savedEquationsPagination');

    let currentSavedPage = 1;
    let deleteEquationId = null;

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

            if (savedEquationsTableBody) {
                savedEquationsTableBody.innerHTML = '';
                const formulaMaxLen = 80;
                list.forEach((eq) => {
                    const tr = document.createElement('tr');
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
                    tr.innerHTML = `
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
                    savedEquationsTableBody.appendChild(tr);
                });
                bindSavedTableActions();
            }

            const hasRows = list.length > 0;
            if (savedEquationsEmpty) {
                savedEquationsEmpty.textContent = pagination?.total === 0
                    ? 'No saved equations yet. Run a regression on the Model Builder page and click Save Equation to store one.'
                    : 'No equations on this page.';
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
                await loadSavedEquationsTable(currentSavedPage);
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
                await loadSavedEquationsTable(currentSavedPage);
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

    document.addEventListener('click', (e) => {
        if (!e.target.closest('.mb-saved-actions-dropdown')) closeAllActionsDropdowns();
    });

    if (typeof lucide !== 'undefined') lucide.createIcons();
});
