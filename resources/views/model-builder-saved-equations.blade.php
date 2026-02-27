@extends('layouts.dashboard')

@section('title', 'Saved Equations')

@section('content')
<div class="dashboard-hub mb-se-page-wrap" id="savedEquationsPage"
    data-saved-equations-index-url="{{ route('saved-equations.index') }}"
    data-saved-equations-base-url="{{ url('saved-equations') }}">
    {{-- Page header: same structure as Model Builder page --}}
    <header class="dashboard-header fade-in-element">
        <div class="header-card mb-header-inner">
            <div class="header-main">
                <div>
                    <a href="{{ route('model-builder') }}" class="mb-se-back-link" aria-label="Back to Model Builder">
                        <i data-lucide="arrow-left" class="lucide-icon lucide-icon-sm" aria-hidden="true"></i>
                        <span>Model Builder</span>
                    </a>
                    <h1 id="mb-se-heading" class="header-title">
                        <i data-lucide="bookmark" class="lucide-icon lucide-icon-md" aria-hidden="true"></i>
                        Saved Equations
                    </h1>
                    <p class="header-location" style="color: rgba(255,255,255,0.9); font-weight: 400;">
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

    {{-- Table card section --}}
    <section class="dashboard-section fade-in-element mb-se-section" aria-labelledby="mb-se-heading">
        <div class="mb-se-card mb-se-table-card">
            <div class="mb-se-card-body mb-se-card-body-compact">
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
    @vite(['resources/css/model-builder.css'])
@endpush

@push('scripts')
    @vite(['resources/js/model-builder-saved-equations.js'])
@endpush
@endsection
