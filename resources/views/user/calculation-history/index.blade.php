@extends('layouts.dashboard')

@section('title', 'Calculation History')

@section('content')
<div class="dashboard-hub mb-se-page-wrap" id="calculationHistoryPage"
    data-delete-url="{{ url('calculation-history') }}"
    data-history-base-url="{{ url('calculation-history') }}"
    data-export-url="{{ route('calculation-history.export') }}"
    data-calculator-url="{{ route('soil-calculator') }}"
    data-has-records="{{ $histories->isNotEmpty() ? '1' : '0' }}"
    data-per-page="{{ $histories->perPage() ?? 10 }}">
    {{-- Page header: title + subtitle only; no back link; clear flex layout --}}
    <header class="dashboard-header fade-in-element">
        <div class="header-card mb-header-inner">
            <div class="header-main ch-header-main">
                <div class="ch-header-content">
                    <h1 id="ch-heading" class="ch-header-title">
                        <i data-lucide="history" class="lucide-icon lucide-icon-md ch-header-icon" aria-hidden="true"></i>
                        <span class="ch-header-title-text">Calculation History</span>
                    </h1>
                    <p class="ch-header-subtitle">
                        View, re-run, and manage past regression calculations
                    </p>
                </div>
            </div>
            <a href="{{ route('soil-calculator') }}" class="mb-btn-view-saved" aria-label="Go to Soil Calculator">
                <i data-lucide="calculator" class="lucide-icon lucide-icon-sm" aria-hidden="true"></i>
                <span>Go to Calculator</span>
            </a>
        </div>
    </header>

    <section class="dashboard-section fade-in-element mb-se-section" aria-labelledby="ch-heading">
        {{-- Filters: same card style as table card --}}
        <div class="mb-se-card ch-filters-card">
            <div class="mb-se-card-body mb-se-card-body-compact">
                <form method="get" action="{{ route('calculation-history.index') }}" class="ch-filters" id="chFiltersForm">
                    <div class="ch-search-wrap">
                        <label for="chSearch" class="sr-only">Search by equation or formula</label>
                        <input type="search" id="chSearch" name="q" value="{{ request('q') }}" placeholder="Search equation name or formula…" class="ch-input ch-search-input" autocomplete="off">
                    </div>
                    <div class="ch-filter-row">
                        <label for="chFrom">From</label>
                        <input type="date" id="chFrom" name="from" value="{{ request('from') }}" class="ch-input ch-input-date">
                    </div>
                    <div class="ch-filter-row">
                        <label for="chTo">To</label>
                        <input type="date" id="chTo" name="to" value="{{ request('to') }}" class="ch-input ch-input-date">
                    </div>
                    <div class="ch-filter-row">
                        <label for="chEquation">Equation</label>
                        <select id="chEquation" name="equation" class="ch-input ch-select">
                            @foreach($equationOptions as $value => $label)
                                <option value="{{ $value }}" {{ request('equation') === (string)$value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ch-filter-row">
                        <label for="chSort">Sort</label>
                        <select id="chSort" name="sort" class="ch-input ch-select">
                            <option value="newest" {{ request('sort', 'newest') === 'newest' ? 'selected' : '' }}>Newest first</option>
                            <option value="oldest" {{ request('sort') === 'oldest' ? 'selected' : '' }}>Oldest first</option>
                        </select>
                    </div>
                    <div class="ch-filter-actions">
                        <button type="submit" class="ch-btn ch-btn-icon" id="chApplyFilters" aria-label="Apply filters">
                            <i data-lucide="check" class="lucide-icon" aria-hidden="true"></i>
                        </button>
                        <button type="button" class="ch-btn ch-btn-icon" id="chClearFilters" aria-label="Clear filters">
                            <i data-lucide="x" class="lucide-icon" aria-hidden="true"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Table card: same structure as Saved Equation page --}}
        <div class="mb-se-card mb-se-table-card">
            <div class="mb-se-card-body mb-se-card-body-compact">
                <div class="mb-se-table-toolbar">
                    <div class="mb-se-toolbar-spacer"></div>
                    <div class="mb-se-export-wrap ch-export-wrap">
                        <button type="button" class="mb-se-export-btn ch-btn ch-btn-primary" id="chExportBtn" aria-label="Export options" aria-haspopup="true" aria-expanded="false" aria-controls="chExportMenu" disabled>
                            <i data-lucide="download" class="lucide-icon lucide-icon-sm" aria-hidden="true"></i>
                            <span>Export</span>
                            <i data-lucide="chevron-down" class="lucide-icon lucide-icon-sm mb-se-export-chevron" aria-hidden="true"></i>
                        </button>
                        <div id="chExportMenu" class="mb-se-export-menu" role="menu" aria-labelledby="chExportBtn" hidden>
                            <button type="button" role="menuitem" class="mb-se-export-item" data-export="pdf" data-scope="page">
                                <svg class="mb-se-export-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M16 13H8"/><path d="M16 17H8"/><path d="M10 9H8"/></svg>
                                <span>PDF – Current page</span>
                            </button>
                            <button type="button" role="menuitem" class="mb-se-export-item" data-export="pdf" data-scope="all">
                                <svg class="mb-se-export-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M16 13H8"/><path d="M16 17H8"/><path d="M10 9H8"/></svg>
                                <span>PDF – All results</span>
                            </button>
                            <button type="button" role="menuitem" class="mb-se-export-item" data-export="excel" data-scope="page">
                                <svg class="mb-se-export-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18" aria-hidden="true"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><path d="M14 2v6h6"/><path d="M8 8h8"/><path d="M8 12h8"/><path d="M8 16h5"/></svg>
                                <span>Excel – Current page</span>
                            </button>
                            <button type="button" role="menuitem" class="mb-se-export-item" data-export="excel" data-scope="all">
                                <svg class="mb-se-export-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18" aria-hidden="true"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><path d="M14 2v6h6"/><path d="M8 8h8"/><path d="M8 12h8"/><path d="M8 16h5"/></svg>
                                <span>Excel – All results</span>
                            </button>
                            <button type="button" role="menuitem" class="mb-se-export-item" data-export="print" data-scope="page">
                                <svg class="mb-se-export-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18" aria-hidden="true"><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><path d="M6 9V3a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v6"/><path d="M6 14h12"/></svg>
                                <span>Print – Current page</span>
                            </button>
                            <button type="button" role="menuitem" class="mb-se-export-item" data-export="print" data-scope="all">
                                <svg class="mb-se-export-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18" aria-hidden="true"><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><path d="M6 9V3a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v6"/><path d="M6 14h12"/></svg>
                                <span>Print – All results</span>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="mb-saved-table-wrap">
                    {{-- Loading overlay (hidden by default, toggled by JS) --}}
                    <div id="chTableLoading" class="ch-table-loading mb-saved-loading hidden" aria-live="polite" aria-busy="true">Loading…</div>

                    {{-- Empty state --}}
                    <div id="chEmpty" class="mb-saved-empty ch-empty-state" aria-live="polite" @if($histories->isNotEmpty()) hidden @endif>
                        <div class="ch-empty-inner">
                            <div class="ch-empty-icon-wrap" aria-hidden="true">
                                <i data-lucide="inbox" class="lucide-icon lucide-icon-xl" aria-hidden="true"></i>
                            </div>
                            <h3 class="ch-empty-title">No calculations yet</h3>
                            <p class="ch-empty-desc">Run a calculation on the Soil Calculator to build your history.</p>
                            <a href="{{ route('soil-calculator') }}" class="ch-empty-cta">
                                <i data-lucide="calculator" class="lucide-icon lucide-icon-sm" aria-hidden="true"></i>
                                Go to Soil Calculator
                            </a>
                        </div>
                    </div>

                    @if($histories->isNotEmpty())
                    {{-- Responsive table wrapper: horizontal scroll on small screens --}}
                    <div class="mb-saved-table-container ch-table-wrap" id="chTableWrap">
                        <table class="mb-saved-table mb-saved-table-styled ch-table w-full min-w-[640px] border-collapse text-left" id="chTable" role="grid" aria-describedby="ch-heading">
                            <thead>
                                <tr>
                                    <th scope="col" class="mb-saved-th-date">Date / Time</th>
                                    <th scope="col" class="mb-saved-th-name">Equation Name</th>
                                    <th scope="col" class="ch-th-inputs">Inputs</th>
                                    <th scope="col" class="ch-th-result">Result</th>
                                    <th scope="col" class="mb-saved-th-actions">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($histories as $index => $h)
                                <tr data-id="{{ $h->id }}" class="ch-row">
                                    <td class="ch-td-date" data-label="Date: ">{{ $h->created_at->format('M j, Y g:i A') }}</td>
                                    <td class="mb-saved-name-cell" data-label="Equation: ">{{ $h->equation_name }}</td>
                                    <td class="ch-td-inputs" data-label="Inputs: ">
                                        @php
                                            $inputCount = is_array($h->inputs) ? count($h->inputs) : 0;
                                        @endphp
                                        <span class="ch-inputs-count">{{ $inputCount }} variable{{ $inputCount !== 1 ? 's' : '' }}</span>
                                    </td>
                                    <td class="ch-td-result" data-label="Result: ">
                                        <span class="ch-result-value">{{ number_format($h->result, 2) }}</span>
                                        <span class="ch-result-unit">m²/year</span>
                                    </td>
                                    <td class="mb-saved-actions-cell" data-label="Actions: ">
                                        <div class="mb-saved-actions-inner">
                                            <button type="button" class="ch-btn-view mb-saved-action-btn mb-saved-action-edit" data-id="{{ $h->id }}" aria-label="View details">
                                                <i data-lucide="eye" class="lucide-icon lucide-icon-sm" aria-hidden="true"></i>
                                                View
                                            </button>
                                            <button type="button" class="ch-btn-delete mb-saved-action-btn mb-saved-action-delete" data-id="{{ $h->id }}" aria-label="Delete">
                                                <i data-lucide="trash-2" class="lucide-icon lucide-icon-sm" aria-hidden="true"></i>
                                                Delete
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if($histories->hasPages())
                    <div class="mb-saved-pagination ch-pagination">
                        {{ $histories->links() }}
                    </div>
                    @endif
                    @endif
                </div>
            </div>
        </div>
    </section>

</div>

{{-- View Calculation Details modal (reuses mb-modal structure from Saved Equations Edit) --}}
<div id="chDetailsModal" class="mb-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="chDetailsModalTitle" aria-describedby="chDetailsDescription" aria-hidden="true" hidden>
    <div class="mb-modal ch-details-modal ch-details-panel">
        <div class="mb-modal-header">
            <h2 id="chDetailsModalTitle" class="mb-modal-title">Calculation Details</h2>
            <button type="button" class="mb-modal-close" id="chDetailsClose" aria-label="Close">&times;</button>
        </div>
        <div class="mb-modal-body">
            <p id="chDetailsDescription" class="sr-only">View calculation inputs, result, and metadata.</p>
            <div id="chDetailsSkeleton" class="ch-details-skeleton hidden" aria-hidden="true">
                <div class="mb-modal-field"><span class="ch-skeleton-line" style="width: 40%;"></span></div>
                <div class="mb-modal-field"><span class="ch-skeleton-line" style="width: 80%;"></span></div>
                <div class="mb-modal-field"><span class="ch-skeleton-line" style="width: 60%;"></span></div>
            </div>
            <div id="chDetailsEmpty" class="ch-details-empty hidden" aria-live="polite">
                <p class="mb-delete-message">Unable to load calculation details.</p>
            </div>
            <div id="chDetailsContent" class="ch-details-content hidden"></div>
        </div>
        <div class="mb-modal-footer">
            <button type="button" class="mb-modal-btn mb-modal-btn-primary" id="chDetailsCloseFooter">Close</button>
        </div>
    </div>
</div>

{{-- Delete confirmation modal --}}
<div id="chDeleteModal" class="mb-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="chDeleteModalTitle" aria-hidden="true" hidden>
    <div class="mb-modal mb-modal-sm">
        <div class="mb-modal-header">
            <h2 id="chDeleteModalTitle" class="mb-modal-title">Delete Calculation</h2>
            <button type="button" class="mb-modal-close" id="chDeleteModalClose" aria-label="Close">&times;</button>
        </div>
        <div class="mb-modal-body">
            <p class="mb-delete-message">Remove this calculation from history? This cannot be undone.</p>
        </div>
        <div class="mb-modal-footer">
            <button type="button" class="mb-modal-btn mb-modal-btn-cancel" id="chDeleteCancel">Cancel</button>
            <button type="button" class="mb-modal-btn mb-modal-btn-danger" id="chDeleteConfirm">Delete</button>
        </div>
    </div>
</div>
@endsection

@push('styles')
    @vite(['resources/views/user/css/model-builder.css'])
    @vite(['resources/views/user/css/calculation-history.css'])
    @vite(['resources/views/user/css/saved-equations.css'])
@endpush

@push('scripts')
    @vite(['resources/views/user/js/calculation-history.js'])
@endpush
