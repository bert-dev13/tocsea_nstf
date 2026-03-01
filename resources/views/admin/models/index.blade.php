@extends('layouts.admin')

@section('title', 'Model Management')
@section('page-title', 'Model Management')

@section('content')
<div class="admin-hub admin-models-page" data-models-base="{{ url('admin/models') }}">
    <header class="admin-header fade-in-element">
        <div class="admin-header-card">
            <div class="admin-header-main">
                <div>
                    <h2 class="admin-header-title">Model Management</h2>
                    <p class="admin-header-meta">
                        <i data-lucide="layers" class="lucide-icon lucide-icon-sm" aria-hidden="true"></i>
                        Manage saved regression models
                    </p>
                </div>
            </div>
        </div>
    </header>

    @if(session('success'))
    <div class="admin-alert admin-alert-success" role="alert">
        <i data-lucide="circle-check-big" class="lucide-icon lucide-icon-sm" aria-hidden="true"></i>
        {{ session('success') }}
    </div>
    @endif
    @if(session('error'))
    <div class="admin-alert admin-alert-error" role="alert">
        <i data-lucide="alert-circle" class="lucide-icon lucide-icon-sm" aria-hidden="true"></i>
        {{ session('error') }}
    </div>
    @endif

    <div id="adminModelsAlert" class="admin-alert admin-alert-success" role="alert" style="display:none;">
        <i data-lucide="circle-check-big" class="lucide-icon lucide-icon-sm" aria-hidden="true"></i>
        <span id="adminModelsAlertText"></span>
    </div>
    <div id="adminModelsErrorAlert" class="admin-alert admin-alert-error" role="alert" style="display:none;">
        <i data-lucide="alert-circle" class="lucide-icon lucide-icon-sm" aria-hidden="true"></i>
        <span id="adminModelsErrorAlertText"></span>
    </div>

    <section class="admin-section admin-users-section fade-in-element">
        <div class="admin-users-toolbar">
            <form method="GET" action="{{ route('admin.models.index') }}" class="admin-users-search-form" id="adminModelsFilterForm" role="search">
                <input type="hidden" name="page" value="1">
                <div class="admin-users-search-wrap">
                    <i data-lucide="search" class="lucide-icon lucide-icon-sm admin-search-icon" aria-hidden="true"></i>
                    <input type="search" name="search" value="{{ request('search') }}" placeholder="Search by equation name…" class="admin-users-search-input" aria-label="Search models">
                </div>
                <input type="date" name="from" value="{{ request('from') }}" class="admin-users-filter-select" aria-label="From date" style="max-width:140px;">
                <input type="date" name="to" value="{{ request('to') }}" class="admin-users-filter-select" aria-label="To date" style="max-width:140px;">
                <select name="sort" class="admin-users-filter-select" aria-label="Sort">
                    <option value="newest" {{ request('sort', 'newest') === 'newest' ? 'selected' : '' }}>Newest first</option>
                    <option value="oldest" {{ request('sort') === 'oldest' ? 'selected' : '' }}>Oldest first</option>
                    <option value="name_asc" {{ request('sort') === 'name_asc' ? 'selected' : '' }}>Name A–Z</option>
                    <option value="name_desc" {{ request('sort') === 'name_desc' ? 'selected' : '' }}>Name Z–A</option>
                </select>
                <button type="submit" class="admin-users-search-btn">Apply</button>
            </form>
        </div>

        <div class="admin-users-table-header">
            <div class="admin-models-export-dropdown no-print">
                <button type="button" class="admin-users-export-dropdown-btn" id="adminModelsExportDropdownBtn" aria-expanded="false" aria-haspopup="true" aria-label="Export options">
                    <i data-lucide="download" class="lucide-icon lucide-icon-sm" aria-hidden="true"></i>
                    Export
                    <i data-lucide="chevron-down" class="lucide-icon lucide-icon-sm admin-users-export-chevron" aria-hidden="true"></i>
                </button>
                <div class="admin-models-export-dropdown-menu" id="adminModelsExportDropdownMenu" role="menu" aria-hidden="true">
                    <a href="{{ route('admin.models.export-excel') }}{{ request()->query() ? '?' . http_build_query(request()->query()) : '' }}" class="admin-users-export-dropdown-item" role="menuitem" id="adminModelsExcelBtn">
                        <i data-lucide="file-spreadsheet" class="lucide-icon lucide-icon-sm" aria-hidden="true"></i>
                        Export as Excel
                    </a>
                    <button type="button" class="admin-users-export-dropdown-item" role="menuitem" id="adminModelsPdfBtn">
                        <i data-lucide="file-text" class="lucide-icon lucide-icon-sm" aria-hidden="true"></i>
                        Export as PDF
                    </button>
                    <button type="button" class="admin-users-export-dropdown-item" role="menuitem" id="adminModelsPrintBtn">
                        <i data-lucide="printer" class="lucide-icon lucide-icon-sm" aria-hidden="true"></i>
                        Print
                    </button>
                </div>
            </div>
        </div>

        <div class="admin-models-table-wrap" id="adminModelsTableWrap">
            <div class="admin-models-print-header" aria-hidden="true">
                <h2 class="admin-models-print-title">TOCSEA – Model Management Report</h2>
                <p class="admin-models-print-date">Generated: <span id="adminModelsPrintDate"></span></p>
            </div>
            <div id="adminModelsTableSkeleton" class="admin-table-skeleton admin-models-skeleton" style="display:none;" aria-hidden="true">
                <table class="admin-models-table"><thead><tr><th>Equation Name</th><th>Formula</th><th>Date Created</th><th>Actions</th></tr></thead><tbody>
                @for ($i = 0; $i < 5; $i++)
                <tr><td><span class="admin-skeleton-line"></span></td><td><span class="admin-skeleton-line"></span></td><td><span class="admin-skeleton-line admin-skeleton-short"></span></td><td><span class="admin-skeleton-line admin-skeleton-short"></span></td></tr>
                @endfor
                </tbody></table>
            </div>
            <div id="adminModelsEmpty" class="admin-models-empty" style="display:none;">
                <i data-lucide="layers" class="lucide-icon lucide-icon-xl" aria-hidden="true"></i>
                <p>No saved models found.</p>
                <p class="admin-users-empty-hint">Models appear here when users save equations from Model Builder. Try adjusting your search or filters.</p>
            </div>
            <table class="admin-models-table" id="adminModelsTable" role="grid" style="display:none;">
                <thead>
                    <tr>
                        <th scope="col">Equation Name</th>
                        <th scope="col" class="admin-models-col-formula">Formula</th>
                        <th scope="col">Date Created</th>
                        <th scope="col" class="admin-models-col-actions no-print">Actions</th>
                    </tr>
                </thead>
                <tbody id="adminModelsTableBody"></tbody>
            </table>
        </div>
        <div id="adminModelsPagination"></div>
    </section>
</div>

{{-- View Modal --}}
<div id="adminViewModelModal" class="admin-modal" role="dialog" aria-modal="true" aria-labelledby="adminViewModelModalTitle" aria-hidden="true">
    <div class="admin-modal-backdrop" data-close-modal></div>
    <div class="admin-modal-dialog admin-modal-dialog-view">
        <div class="admin-modal-header admin-view-model-header">
            <div class="admin-view-model-header-inner">
                <h3 id="adminViewModelModalTitle" class="admin-modal-title">Model Details</h3>
                <p id="adminViewModelSubtitle" class="admin-view-model-subtitle" aria-hidden="true">…</p>
            </div>
            <button type="button" class="admin-modal-close" data-close-modal aria-label="Close">
                <i data-lucide="x" class="lucide-icon lucide-icon-md" aria-hidden="true"></i>
            </button>
        </div>
        <div class="admin-modal-body admin-view-model-body">
            <div id="adminViewModelContent" class="admin-modal-view-content">
                <div class="admin-modal-loading">
                    <i data-lucide="loader-2" class="lucide-icon lucide-icon-lg admin-spin" aria-hidden="true"></i>
                    <span>Loading…</span>
                </div>
            </div>
        </div>
        <div class="admin-modal-footer admin-view-model-footer">
            <button type="button" class="admin-modal-btn admin-modal-btn-secondary" data-close-modal>Close</button>
        </div>
    </div>
</div>

{{-- Delete Modal --}}
<div id="adminDeleteModelModal" class="admin-modal" role="dialog" aria-modal="true" aria-labelledby="adminDeleteModelModalTitle" aria-hidden="true">
    <div class="admin-modal-backdrop" data-close-modal></div>
    <div class="admin-modal-dialog admin-modal-dialog-sm">
        <div class="admin-modal-header">
            <h3 id="adminDeleteModelModalTitle" class="admin-modal-title">Delete Model</h3>
            <button type="button" class="admin-modal-close" data-close-modal aria-label="Close">
                <i data-lucide="x" class="lucide-icon lucide-icon-md" aria-hidden="true"></i>
            </button>
        </div>
        <div class="admin-modal-body">
            <p class="admin-delete-warning">
                <i data-lucide="alert-triangle" class="lucide-icon lucide-icon-md" aria-hidden="true"></i>
                Are you sure you want to delete this model? This action cannot be undone.
            </p>
            <dl class="admin-delete-info">
                <dt>Model</dt>
                <dd id="adminDeleteModelName"></dd>
            </dl>
        </div>
        <div class="admin-modal-footer">
            <button type="button" class="admin-modal-btn admin-modal-btn-secondary" data-close-modal>Cancel</button>
            <button type="button" id="adminDeleteModelConfirmBtn" class="admin-modal-btn admin-modal-btn-danger">Confirm Delete</button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
window.ADMIN_MODELS_CONFIG = {
    csrfToken: @json(csrf_token()),
    routes: {
        show: @json(route('admin.models.show', ['saved_equation' => '__ID__'])),
        destroy: @json(route('admin.models.destroy', ['saved_equation' => '__ID__'])),
    }
};
</script>
@endpush