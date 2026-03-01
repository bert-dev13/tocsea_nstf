@extends('layouts.admin')

@section('title', 'Calculation Monitoring')
@section('page-title', 'Calculation Monitoring')

@section('content')
<div class="admin-hub admin-calculations-page" data-admin-calculations-base="{{ route('admin.calculations.index') }}">
    <header class="admin-header fade-in-element">
        <div class="admin-header-card">
            <div class="admin-header-main">
                <div>
                    <h2 class="admin-header-title">Calculation Monitoring</h2>
                    <p class="admin-header-meta">
                        <i data-lucide="calculator" class="lucide-icon lucide-icon-sm" aria-hidden="true"></i>
                        All Soil Calculator runs (system-wide)
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

    <div id="adminCalculationsAlert" class="admin-alert admin-alert-success" role="alert" style="display:none;">
        <i data-lucide="circle-check-big" class="lucide-icon lucide-icon-sm" aria-hidden="true"></i>
        <span id="adminCalculationsAlertText"></span>
    </div>
    <div id="adminCalculationsErrorAlert" class="admin-alert admin-alert-error" role="alert" style="display:none;">
        <i data-lucide="alert-circle" class="lucide-icon lucide-icon-sm" aria-hidden="true"></i>
        <span id="adminCalculationsErrorAlertText"></span>
    </div>

    <section class="admin-section admin-users-section fade-in-element">
        <div class="admin-users-toolbar">
            <form method="GET" action="{{ route('admin.calculations.index') }}" class="admin-users-search-form" id="adminCalculationsFilterForm" role="search">
                <input type="hidden" name="page" value="1">
                <div class="admin-users-search-wrap">
                    <i data-lucide="search" class="lucide-icon lucide-icon-sm admin-search-icon" aria-hidden="true"></i>
                    <input type="search" name="search" value="{{ request('search') }}" placeholder="Search user, location, equation…" class="admin-users-search-input" aria-label="Search calculations">
                </div>
                <input type="date" name="from" value="{{ request('from') }}" class="admin-users-filter-select" aria-label="From date" style="max-width:140px;">
                <input type="date" name="to" value="{{ request('to') }}" class="admin-users-filter-select" aria-label="To date" style="max-width:140px;">
                <select name="risk" class="admin-users-filter-select" aria-label="Risk level">
                    <option value="">All risk levels</option>
                    <option value="low" {{ request('risk') === 'low' ? 'selected' : '' }}>Low</option>
                    <option value="moderate" {{ request('risk') === 'moderate' ? 'selected' : '' }}>Moderate</option>
                    <option value="high" {{ request('risk') === 'high' ? 'selected' : '' }}>High</option>
                </select>
                <select name="equation" class="admin-users-filter-select" aria-label="Equation/Model">
                    @foreach($equationOptions as $value => $label)
                        <option value="{{ $value }}" {{ request('equation') === (string)$value ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                <select name="sort" class="admin-users-filter-select" aria-label="Sort">
                    <option value="newest" {{ request('sort', 'newest') === 'newest' ? 'selected' : '' }}>Newest first</option>
                    <option value="oldest" {{ request('sort') === 'oldest' ? 'selected' : '' }}>Oldest first</option>
                    <option value="loss_desc" {{ request('sort') === 'loss_desc' ? 'selected' : '' }}>Highest loss first</option>
                    <option value="loss_asc" {{ request('sort') === 'loss_asc' ? 'selected' : '' }}>Lowest loss first</option>
                </select>
                <button type="submit" class="admin-users-search-btn">Apply</button>
            </form>
        </div>

        <div class="admin-users-table-header">
            <div class="admin-calculations-export-dropdown no-print">
                <button type="button" class="admin-users-export-dropdown-btn" id="adminCalculationsExportDropdownBtn" aria-expanded="false" aria-haspopup="true" aria-label="Export options">
                    <i data-lucide="download" class="lucide-icon lucide-icon-sm" aria-hidden="true"></i>
                    Export
                    <i data-lucide="chevron-down" class="lucide-icon lucide-icon-sm admin-users-export-chevron" aria-hidden="true"></i>
                </button>
                <div class="admin-calculations-export-dropdown-menu" id="adminCalculationsExportDropdownMenu" role="menu" aria-hidden="true">
                    <a href="{{ route('admin.calculations.export-excel') }}{{ request()->query() ? '?' . http_build_query(request()->query()) : '' }}" class="admin-users-export-dropdown-item" role="menuitem" id="adminCalculationsExcelBtn">
                        <i data-lucide="file-spreadsheet" class="lucide-icon lucide-icon-sm" aria-hidden="true"></i>
                        Export as Excel
                    </a>
                    <button type="button" class="admin-users-export-dropdown-item" role="menuitem" id="adminCalculationsPdfBtn">
                        <i data-lucide="file-text" class="lucide-icon lucide-icon-sm" aria-hidden="true"></i>
                        Export as PDF
                    </button>
                    <button type="button" class="admin-users-export-dropdown-item" role="menuitem" id="adminCalculationsPrintBtn">
                        <i data-lucide="printer" class="lucide-icon lucide-icon-sm" aria-hidden="true"></i>
                        Print
                    </button>
                </div>
            </div>
        </div>

        <div class="admin-users-table-wrap" id="adminCalculationsTableWrap">
            <div class="admin-calculations-print-header" aria-hidden="true">
                <h2 class="admin-calculations-print-title">TOCSEA – Calculation Monitoring Report</h2>
                <p class="admin-calculations-print-date">Generated: <span id="adminCalculationsPrintDate"></span></p>
            </div>
            <div id="adminCalculationsTableSkeleton" class="admin-table-skeleton" style="display:none;" aria-hidden="true">
                <table class="admin-users-table admin-calculations-table"><thead><tr><th>Date/Time</th><th>User</th><th>Location</th><th>Model</th><th>Soil Loss</th><th>Risk</th><th>Status</th><th>Actions</th></tr></thead><tbody>
                @for ($i = 0; $i < 5; $i++)
                <tr><td><span class="admin-skeleton-line"></span></td><td><span class="admin-skeleton-line"></span></td><td><span class="admin-skeleton-line"></span></td><td><span class="admin-skeleton-line admin-skeleton-short"></span></td><td><span class="admin-skeleton-line admin-skeleton-short"></span></td><td><span class="admin-skeleton-line admin-skeleton-short"></span></td><td><span class="admin-skeleton-line admin-skeleton-short"></span></td><td><span class="admin-skeleton-line admin-skeleton-short"></span></td></tr>
                @endfor
                </tbody></table>
            </div>
            <div id="adminCalculationsEmpty" class="admin-users-empty" style="display:none;">
                <i data-lucide="calculator" class="lucide-icon lucide-icon-xl" aria-hidden="true"></i>
                <p>No calculations found</p>
                <p class="admin-users-empty-hint">Try adjusting your search or filters.</p>
            </div>
            <table class="admin-users-table admin-calculations-table" id="adminCalculationsTable" role="grid" style="display:none;">
                <thead>
                    <tr>
                        <th scope="col">Date/Time</th>
                        <th scope="col">User</th>
                        <th scope="col">Location</th>
                        <th scope="col">Model/Equation</th>
                        <th scope="col">Predicted Soil Loss</th>
                        <th scope="col">Risk Level</th>
                        <th scope="col">Status</th>
                        <th scope="col" class="admin-users-col-actions no-print">Actions</th>
                    </tr>
                </thead>
                <tbody id="adminCalculationsTableBody"></tbody>
            </table>
        </div>
        <div id="adminCalculationsPagination"></div>
    </section>
</div>

{{-- View Modal --}}
<div id="adminViewCalculationModal" class="admin-modal" role="dialog" aria-modal="true" aria-labelledby="adminViewCalculationModalTitle" aria-hidden="true">
    <div class="admin-modal-backdrop" data-close-modal></div>
    <div class="admin-modal-dialog admin-modal-dialog-view admin-modal-dialog-md">
        <div class="admin-modal-header admin-view-user-header">
            <div class="admin-view-user-header-inner">
                <h3 id="adminViewCalculationModalTitle" class="admin-modal-title">Calculation Details</h3>
                <p id="adminViewCalculationSubtitle" class="admin-view-user-subtitle" aria-hidden="true">Viewing calculation…</p>
            </div>
            <button type="button" class="admin-modal-close" data-close-modal aria-label="Close">
                <i data-lucide="x" class="lucide-icon lucide-icon-md" aria-hidden="true"></i>
            </button>
        </div>
        <div class="admin-modal-body admin-view-user-body">
            <div id="adminViewCalculationContent" class="admin-modal-view-content">
                <div class="admin-modal-loading">
                    <i data-lucide="loader-2" class="lucide-icon lucide-icon-lg admin-spin" aria-hidden="true"></i>
                    <span>Loading…</span>
                </div>
            </div>
        </div>
        <div class="admin-modal-footer admin-view-user-footer">
            <button type="button" class="admin-modal-btn admin-modal-btn-secondary" data-close-modal>Close</button>
        </div>
    </div>
</div>

{{-- Delete Modal --}}
<div id="adminDeleteCalculationModal" class="admin-modal" role="dialog" aria-modal="true" aria-labelledby="adminDeleteCalculationModalTitle" aria-hidden="true">
    <div class="admin-modal-backdrop" data-close-modal></div>
    <div class="admin-modal-dialog admin-modal-dialog-sm">
        <div class="admin-modal-header">
            <h3 id="adminDeleteCalculationModalTitle" class="admin-modal-title">Delete Calculation</h3>
            <button type="button" class="admin-modal-close" data-close-modal aria-label="Close">
                <i data-lucide="x" class="lucide-icon lucide-icon-md" aria-hidden="true"></i>
            </button>
        </div>
        <div class="admin-modal-body">
            <p class="admin-delete-warning">
                <i data-lucide="alert-triangle" class="lucide-icon lucide-icon-md" aria-hidden="true"></i>
                Are you sure you want to delete this calculation? This action cannot be undone.
            </p>
            <dl class="admin-delete-info">
                <dt>Equation</dt>
                <dd id="adminDeleteCalculationEquation"></dd>
            </dl>
        </div>
        <div class="admin-modal-footer">
            <button type="button" class="admin-modal-btn admin-modal-btn-secondary" data-close-modal>Cancel</button>
            <button type="button" id="adminDeleteCalculationConfirmBtn" class="admin-modal-btn admin-modal-btn-danger">Confirm Delete</button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
window.ADMIN_CALCULATIONS_CONFIG = {
    csrfToken: @json(csrf_token()),
    routes: {
        show: @json(route('admin.calculations.show', ['calculation_history' => '__ID__'])),
        destroy: @json(route('admin.calculations.destroy', ['calculation_history' => '__ID__'])),
    }
};
</script>
@endpush
