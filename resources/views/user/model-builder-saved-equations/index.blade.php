@extends('layouts.dashboard')

@section('title', 'Saved Equations')

@section('content')
<div class="dashboard-hub mb-se-page-wrap" id="savedEquationsPage"
    data-saved-equations-index-url="{{ route('saved-equations.index') }}"
    data-saved-equations-base-url="{{ url('saved-equations') }}">
    {{-- Page header: title + subtitle + action button; clean layout --}}
    <header class="dashboard-header fade-in-element">
        <div class="header-card mb-header-inner">
            <div class="header-main ch-header-main">
                <div class="ch-header-content">
                    <h1 id="mb-se-heading" class="ch-header-title">
                        <i data-lucide="bookmark" class="lucide-icon lucide-icon-md ch-header-icon" aria-hidden="true"></i>
                        <span class="ch-header-title-text">Saved Equations</span>
                    </h1>
                    <p class="ch-header-subtitle">
                        View and manage stored regression equations
                    </p>
                </div>
            </div>
            <a href="{{ route('model-builder') }}" class="mb-btn-view-saved" aria-label="Add new equation">
                <i data-lucide="plus" class="lucide-icon lucide-icon-sm" aria-hidden="true"></i>
                <span>Add New Equation</span>
            </a>
        </div>
    </header>

    {{-- Filters: same layout and style as Calculation History --}}
    <section class="dashboard-section fade-in-element mb-se-section" aria-labelledby="mb-se-heading">
        <div class="mb-se-card ch-filters-card">
            <div class="mb-se-card-body mb-se-card-body-compact">
                <div class="ch-filters" id="savedEquationsFilters">
                    <div class="ch-search-wrap">
                        <label for="seSearch" class="sr-only">Search by equation name or formula</label>
                        <input type="search" id="seSearch" placeholder="Search equation name or formula…" class="ch-input ch-search-input" autocomplete="off" aria-label="Search by equation name or formula">
                    </div>
                    <div class="ch-filter-row">
                        <label for="seDateFrom">From</label>
                        <input type="date" id="seDateFrom" class="ch-input ch-input-date" aria-label="Filter from date">
                    </div>
                    <div class="ch-filter-row">
                        <label for="seDateTo">To</label>
                        <input type="date" id="seDateTo" class="ch-input ch-input-date" aria-label="Filter to date">
                    </div>
                    <div class="ch-filter-row">
                        <label for="seSort">Sort</label>
                        <select id="seSort" class="ch-input ch-select" aria-label="Sort order">
                            <option value="newest">Newest Created</option>
                            <option value="oldest">Oldest Created</option>
                            <option value="updated">Recently Updated</option>
                            <option value="name_az">Name A–Z</option>
                            <option value="name_za">Name Z–A</option>
                        </select>
                    </div>
                    <div class="ch-filter-actions">
                        <button type="button" class="ch-btn ch-btn-icon" id="seApplyFilters" aria-label="Apply filters">
                            <svg class="ch-btn-icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                        </button>
                        <button type="button" class="ch-btn ch-btn-icon" id="seClearFilters" aria-label="Clear filters">
                            <svg class="ch-btn-icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="mb-se-card mb-se-table-card">
            <div class="mb-se-card-body mb-se-card-body-compact">
                <div class="mb-se-table-toolbar">
                    <div class="mb-se-toolbar-spacer"></div>
                    <div class="mb-se-export-wrap">
                        <button type="button" class="mb-se-export-btn ch-btn ch-btn-primary" id="savedEquationsExportBtn" aria-label="Export options" aria-haspopup="true" aria-expanded="false" aria-controls="savedEquationsExportMenu" disabled>
                            <i data-lucide="download" class="lucide-icon lucide-icon-sm" aria-hidden="true"></i>
                            <span>Export</span>
                            <i data-lucide="chevron-down" class="lucide-icon lucide-icon-sm mb-se-export-chevron" aria-hidden="true"></i>
                        </button>
                        <div id="savedEquationsExportMenu" class="mb-se-export-menu" role="menu" aria-labelledby="savedEquationsExportBtn" hidden>
                            <button type="button" role="menuitem" class="mb-se-export-item" data-export="excel">
                                <svg class="mb-se-export-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18" aria-hidden="true"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><path d="M14 2v6h6"/><path d="M8 8h8"/><path d="M8 12h8"/><path d="M8 16h5"/></svg>
                                <span>Export as Excel</span>
                            </button>
                            <button type="button" role="menuitem" class="mb-se-export-item" data-export="pdf">
                                <svg class="mb-se-export-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M16 13H8"/><path d="M16 17H8"/><path d="M10 9H8"/></svg>
                                <span>Export PDF</span>
                            </button>
                            <button type="button" role="menuitem" class="mb-se-export-item" data-export="print">
                                <svg class="mb-se-export-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18" aria-hidden="true"><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><path d="M6 9V3a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v6"/><path d="M6 14h12"/></svg>
                                <span>Print</span>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="mb-saved-table-wrap">
                    <div id="savedEquationsEmpty" class="mb-saved-empty">No saved equations yet. Run a regression on the Model Builder page and click Save Equation to store one.</div>
                    <div id="savedEquationsLoading" class="mb-saved-loading" hidden>Loading…</div>
                    <div id="savedEquationsTableWrap" class="mb-saved-table-container" hidden>
                        <table class="mb-saved-table mb-saved-table-styled" id="savedEquationsTable">
                            <thead>
                                <tr>
                                    <th class="mb-saved-th-name">Equation Name</th>
                                    <th class="mb-saved-th-formula">Formula</th>
                                    <th class="mb-saved-th-date mb-saved-th-date-center">Date Created</th>
                                    <th class="mb-saved-th-date mb-saved-th-date-center">Last Updated</th>
                                    <th class="mb-saved-th-actions">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="savedEquationsTableBody"></tbody>
                        </table>
                    </div>
                    <div id="savedEquationsPagination" class="mb-saved-pagination" hidden></div>
                </div>
            </div>
        </div>
    </section>

    {{-- Edit Equation modal --}}
    <div id="editEquationModal" class="mb-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="editEquationModalTitle" aria-hidden="true" hidden>
        <div class="mb-modal">
            <div class="mb-modal-header">
                <h2 id="editEquationModalTitle" class="mb-modal-title">Edit Equation</h2>
                <button type="button" class="mb-modal-close" id="editEquationModalClose" aria-label="Close">&times;</button>
            </div>
            <form id="editEquationForm" class="mb-modal-body">
                <input type="hidden" id="editEquationId" name="id" value="">
                <div class="mb-modal-field">
                    <label for="editEquationName">Equation Name <span class="required-star">*</span></label>
                    <input type="text" id="editEquationName" name="equation_name" required maxlength="255" placeholder="e.g. Coastal 2024 Model" autocomplete="off">
                    <span id="editEquationNameError" class="mb-modal-error" role="alert" hidden></span>
                </div>
                <div class="mb-modal-field">
                    <label for="editEquationFormula">Formula <span class="required-star">*</span></label>
                    <textarea id="editEquationFormula" name="formula" rows="6" class="mb-formula-editable"></textarea>
                </div>
                <div id="editEquationFormError" class="mb-modal-error mb-modal-error-block" role="alert" hidden></div>
            </form>
            <div class="mb-modal-footer">
                <button type="button" class="mb-modal-btn mb-modal-btn-cancel" id="editEquationCancel">Cancel</button>
                <button type="submit" form="editEquationForm" class="mb-modal-btn mb-modal-btn-primary" id="editEquationSubmit">Update</button>
            </div>
        </div>
    </div>

    {{-- Delete Equation confirmation modal --}}
    <div id="deleteEquationModal" class="mb-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="deleteEquationModalTitle" aria-hidden="true" hidden>
        <div class="mb-modal mb-modal-sm">
            <div class="mb-modal-header">
                <h2 id="deleteEquationModalTitle" class="mb-modal-title">Delete Equation</h2>
                <button type="button" class="mb-modal-close" id="deleteEquationModalClose" aria-label="Close">&times;</button>
            </div>
            <div class="mb-modal-body">
                <p id="deleteEquationMessage" class="mb-delete-message">Are you sure you want to delete this equation? This cannot be undone.</p>
            </div>
            <div class="mb-modal-footer">
                <button type="button" class="mb-modal-btn mb-modal-btn-cancel" id="deleteEquationCancel">Cancel</button>
                <button type="button" class="mb-modal-btn mb-modal-btn-danger" id="deleteEquationConfirm">Delete</button>
            </div>
        </div>
    </div>
</div>

@push('styles')
    @vite(['resources/views/user/css/calculation-history.css'])
    @vite(['resources/views/user/css/saved-equations.css'])
@endpush

@push('scripts')
    @vite(['resources/views/user/js/saved-equations.js'])
@endpush
@endsection
