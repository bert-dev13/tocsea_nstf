@extends('layouts.admin')

@section('title', 'Admin Analytics')
@section('page-title', 'Analytics')

@section('content')
<div class="admin-hub admin-analytics">
    <header class="admin-header fade-in-element">
        <div class="admin-header-card">
            <div class="admin-header-main">
                <div>
                    <h2 class="admin-header-title">Analytics</h2>
                    <p class="admin-header-meta">
                        <i data-lucide="bar-chart-2" class="lucide-icon lucide-icon-sm" aria-hidden="true"></i>
                        System usage, charts, and geographic insights
                    </p>
                </div>
                <div class="admin-header-badge">
                    <i data-lucide="activity" class="lucide-icon lucide-icon-sm" aria-hidden="true"></i>
                    <span>Live data</span>
                </div>
            </div>
        </div>
    </header>

    {{-- System Usage Overview --}}
    <section class="admin-section admin-stats-section fade-in-element">
        <h3 class="admin-section-title"><i data-lucide="bar-chart-2" class="lucide-icon lucide-icon-md" aria-hidden="true"></i> System Usage Overview</h3>
        <div class="admin-stats-grid admin-analytics-stats">
            <article class="admin-stat-card">
                <i data-lucide="users" class="lucide-icon admin-stat-icon" aria-hidden="true"></i>
                <span class="admin-stat-value">{{ number_format($stats['totalUsers']) }}</span>
                <span class="admin-stat-label">Total Users</span>
            </article>
            <article class="admin-stat-card">
                <i data-lucide="shield" class="lucide-icon admin-stat-icon" aria-hidden="true"></i>
                <span class="admin-stat-value">{{ number_format($stats['adminUsers']) }}</span>
                <span class="admin-stat-label">Admins</span>
            </article>
            <article class="admin-stat-card">
                <i data-lucide="user" class="lucide-icon admin-stat-icon" aria-hidden="true"></i>
                <span class="admin-stat-value">{{ number_format($stats['regularUsers']) }}</span>
                <span class="admin-stat-label">Regular Users</span>
            </article>
            <article class="admin-stat-card">
                <i data-lucide="calculator" class="lucide-icon admin-stat-icon" aria-hidden="true"></i>
                <span class="admin-stat-value">{{ number_format($stats['totalCalculations']) }}</span>
                <span class="admin-stat-label">Total Calculations</span>
            </article>
            <article class="admin-stat-card">
                <i data-lucide="box" class="lucide-icon admin-stat-icon" aria-hidden="true"></i>
                <span class="admin-stat-value">{{ number_format($stats['totalSavedModels']) }}</span>
                <span class="admin-stat-label">Saved Models</span>
            </article>
            <article class="admin-stat-card">
                <i data-lucide="user-plus" class="lucide-icon admin-stat-icon" aria-hidden="true"></i>
                <span class="admin-stat-value">{{ number_format($stats['newUsersThisMonth']) }}</span>
                <span class="admin-stat-label">New Users This Month</span>
            </article>
        </div>
    </section>

    {{-- Interactive Charts --}}
    <section class="admin-section admin-charts-section fade-in-element">
        <h3 class="admin-section-title"><i data-lucide="trending-up" class="lucide-icon lucide-icon-md" aria-hidden="true"></i> Interactive Charts</h3>
        <div class="admin-charts-grid">
            <div class="admin-chart-card">
                <h4 class="admin-chart-title">Calculations per Month</h4>
                @if(array_sum($calculationsPerMonthData) > 0)
                    <div class="admin-chart-wrap">
                        <canvas id="chartCalculationsPerMonth" height="280"></canvas>
                    </div>
                @else
                    <div class="admin-chart-empty">
                        <i data-lucide="inbox" class="lucide-icon lucide-icon-lg" aria-hidden="true"></i>
                        <p>No calculation data yet</p>
                    </div>
                @endif
            </div>
            <div class="admin-chart-card">
                <h4 class="admin-chart-title">User Registrations per Month</h4>
                @if(array_sum($registrationsPerMonthData) > 0)
                    <div class="admin-chart-wrap">
                        <canvas id="chartRegistrationsPerMonth" height="280"></canvas>
                    </div>
                @else
                    <div class="admin-chart-empty">
                        <i data-lucide="inbox" class="lucide-icon lucide-icon-lg" aria-hidden="true"></i>
                        <p>No registration data yet</p>
                    </div>
                @endif
            </div>
            <div class="admin-chart-card">
                <h4 class="admin-chart-title">Most Used Prediction Model</h4>
                @if($mostUsedModels->isNotEmpty())
                    <div class="admin-chart-wrap admin-chart-wrap-pie">
                        <canvas id="chartMostUsedModel" height="280"></canvas>
                    </div>
                @else
                    <div class="admin-chart-empty">
                        <i data-lucide="inbox" class="lucide-icon lucide-icon-lg" aria-hidden="true"></i>
                        <p>No model usage data yet</p>
                    </div>
                @endif
            </div>
            <div class="admin-chart-card">
                <h4 class="admin-chart-title">Soil Risk Level Distribution</h4>
                @if(array_sum($riskDistribution) > 0)
                    <div class="admin-chart-wrap admin-chart-wrap-pie">
                        <canvas id="chartRiskDistribution" height="280"></canvas>
                    </div>
                @else
                    <div class="admin-chart-empty">
                        <i data-lucide="inbox" class="lucide-icon lucide-icon-lg" aria-hidden="true"></i>
                        <p>No risk data yet</p>
                    </div>
                @endif
            </div>
        </div>
    </section>

    {{-- Geographic Insights --}}
    <section class="admin-section admin-geo-section fade-in-element">
        <h3 class="admin-section-title"><i data-lucide="map-pin" class="lucide-icon lucide-icon-md" aria-hidden="true"></i> Geographic Insights</h3>
        <div class="admin-geo-grid">
            <div class="admin-recent-card admin-geo-card">
                <h4 class="admin-geo-card-title">Top Provinces</h4>
                @if($topProvinces->isEmpty())
                    <div class="admin-recent-empty">
                        <i data-lucide="inbox" class="lucide-icon lucide-icon-lg" aria-hidden="true"></i>
                        <p>No province data yet</p>
                    </div>
                @else
                    <ul class="admin-geo-list">
                        @foreach($topProvinces as $idx => $row)
                        <li class="admin-geo-item">
                            <span class="admin-geo-rank">{{ $idx + 1 }}</span>
                            <span class="admin-geo-name">{{ $row->province ?: '—' }}</span>
                            <span class="admin-geo-count">{{ number_format($row->total) }} users</span>
                        </li>
                        @endforeach
                    </ul>
                @endif
            </div>
            <div class="admin-recent-card admin-geo-card">
                <h4 class="admin-geo-card-title">Most Active Municipalities</h4>
                @if($topMunicipalities->isEmpty())
                    <div class="admin-recent-empty">
                        <i data-lucide="inbox" class="lucide-icon lucide-icon-lg" aria-hidden="true"></i>
                        <p>No municipality data yet</p>
                    </div>
                @else
                    <ul class="admin-geo-list">
                        @foreach($topMunicipalities as $idx => $row)
                        <li class="admin-geo-item">
                            <span class="admin-geo-rank">{{ $idx + 1 }}</span>
                            <span class="admin-geo-name">{{ $row->municipality ?: '—' }}{{ $row->province ? ', ' . $row->province : '' }}</span>
                            <span class="admin-geo-count">{{ number_format($row->total) }} users</span>
                        </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </section>

    {{-- Recent Activity Summary --}}
    <section class="admin-section admin-activity-section fade-in-element">
        <h3 class="admin-section-title"><i data-lucide="clock" class="lucide-icon lucide-icon-md" aria-hidden="true"></i> Recent Activity Summary</h3>
        <div class="admin-activity-grid">
            <div class="admin-recent-card admin-activity-card">
                <h4 class="admin-activity-card-title">Latest Calculations</h4>
                @if($latestCalculations->isEmpty())
                    <div class="admin-recent-empty">
                        <i data-lucide="inbox" class="lucide-icon lucide-icon-lg" aria-hidden="true"></i>
                        <p>No calculations yet</p>
                    </div>
                @else
                    <ul class="admin-recent-list">
                        @foreach($latestCalculations->take(5) as $c)
                        <li class="admin-recent-item">
                            <div class="admin-recent-item-main">
                                <span class="admin-recent-item-name">{{ $c->equation_name ?: 'Calculation' }}</span>
                                <span class="admin-recent-item-email">{{ $c->user?->name ?? '—' }} · {{ $c->created_at->diffForHumans() }}</span>
                            </div>
                            <a href="{{ route('admin.calculations.index') }}?search={{ urlencode($c->user?->email ?? '') }}" class="admin-recent-item-link" aria-label="View calculations">
                                <i data-lucide="eye" class="lucide-icon lucide-icon-sm" aria-hidden="true"></i>
                                View
                            </a>
                        </li>
                        @endforeach
                    </ul>
                    <a href="{{ route('admin.calculations.index') }}" class="admin-recent-more">View all calculations →</a>
                @endif
            </div>
            <div class="admin-recent-card admin-activity-card">
                <h4 class="admin-activity-card-title">Latest Registered Users</h4>
                @if($latestRegisteredUsers->isEmpty())
                    <div class="admin-recent-empty">
                        <i data-lucide="inbox" class="lucide-icon lucide-icon-lg" aria-hidden="true"></i>
                        <p>No users yet</p>
                    </div>
                @else
                    <ul class="admin-recent-list">
                        @foreach($latestRegisteredUsers->take(5) as $u)
                        <li class="admin-recent-item">
                            <div class="admin-recent-item-main">
                                <span class="admin-recent-item-name">{{ $u->name }}</span>
                                <span class="admin-recent-item-email">{{ $u->email }}</span>
                            </div>
                            <a href="{{ route('admin.users.show', $u) }}" class="admin-recent-item-link" aria-label="View {{ $u->name }}">
                                <i data-lucide="eye" class="lucide-icon lucide-icon-sm" aria-hidden="true"></i>
                                View
                            </a>
                        </li>
                        @endforeach
                    </ul>
                    <a href="{{ route('admin.users.index') }}" class="admin-recent-more">View all users →</a>
                @endif
            </div>
            <div class="admin-recent-card admin-activity-card">
                <h4 class="admin-activity-card-title">Recently Saved Models</h4>
                @if($recentlySavedModels->isEmpty())
                    <div class="admin-recent-empty">
                        <i data-lucide="inbox" class="lucide-icon lucide-icon-lg" aria-hidden="true"></i>
                        <p>No saved models yet</p>
                    </div>
                @else
                    <ul class="admin-recent-list">
                        @foreach($recentlySavedModels->take(5) as $m)
                        <li class="admin-recent-item">
                            <div class="admin-recent-item-main">
                                <span class="admin-recent-item-name">{{ Str::limit($m->equation_name, 32) }}</span>
                                <span class="admin-recent-item-email">{{ $m->user?->name ?? '—' }} · {{ $m->created_at->diffForHumans() }}</span>
                            </div>
                            <a href="{{ route('admin.models.index') }}" class="admin-recent-item-link" aria-label="View models">
                                <i data-lucide="layers" class="lucide-icon lucide-icon-sm" aria-hidden="true"></i>
                                View
                            </a>
                        </li>
                        @endforeach
                    </ul>
                    <a href="{{ route('admin.models.index') }}" class="admin-recent-more">View all models →</a>
                @endif
            </div>
        </div>
    </section>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" crossorigin="anonymous"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const chartOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false }
        },
        scales: {}
    };
    const teal = 'rgb(14, 107, 107)';
    const tealLight = 'rgb(20, 163, 163)';
    const ocean = 'rgb(13, 61, 86)';
    const colors = [teal, tealLight, ocean, 'rgb(14, 107, 107, 0.7)', 'rgb(20, 163, 163, 0.7)'];

    // Calculations per month (line)
    const calcCtx = document.getElementById('chartCalculationsPerMonth');
    if (calcCtx && @json(array_sum($calculationsPerMonthData) > 0)) {
        new Chart(calcCtx, {
            type: 'line',
            data: {
                labels: @json($calculationsPerMonthLabels),
                datasets: [{
                    label: 'Calculations',
                    data: @json($calculationsPerMonthData),
                    borderColor: teal,
                    backgroundColor: 'rgba(14, 107, 107, 0.1)',
                    fill: true,
                    tension: 0.3
                }]
            },
            options: {
                ...chartOptions,
                scales: {
                    y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.06)' } },
                    x: { grid: { display: false } }
                }
            }
        });
    }

    // Registrations per month (bar)
    const regCtx = document.getElementById('chartRegistrationsPerMonth');
    if (regCtx && @json(array_sum($registrationsPerMonthData) > 0)) {
        new Chart(regCtx, {
            type: 'bar',
            data: {
                labels: @json($registrationsPerMonthLabels),
                datasets: [{
                    label: 'Registrations',
                    data: @json($registrationsPerMonthData),
                    backgroundColor: tealLight,
                    borderColor: teal,
                    borderWidth: 1
                }]
            },
            options: {
                ...chartOptions,
                scales: {
                    y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.06)' } },
                    x: { grid: { display: false } }
                }
            }
        });
    }

    // Most used model (pie)
    const modelCtx = document.getElementById('chartMostUsedModel');
    if (modelCtx && @json($mostUsedModels->isNotEmpty())) {
        new Chart(modelCtx, {
            type: 'doughnut',
            data: {
                labels: @json($mostUsedModels->map(fn($m) => \Illuminate\Support\Str::limit($m->equation_name, 28))->values()),
                datasets: [{
                    data: @json($mostUsedModels->pluck('usage_count')->values()),
                    backgroundColor: ['#0e6b6b', '#14a3a3', '#0d3d56', '#0a2540', 'rgba(14,107,107,0.7)', 'rgba(20,163,163,0.7)', 'rgba(13,61,86,0.7)', 'rgba(10,37,64,0.7)'],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });
    }

    // Risk distribution (pie)
    const riskCtx = document.getElementById('chartRiskDistribution');
    const riskData = @json($riskDistribution);
    const riskTotal = Object.values(riskData).reduce((a, b) => a + b, 0);
    if (riskCtx && riskTotal > 0) {
        const riskLabels = Object.keys(riskData);
        const riskValues = Object.values(riskData);
        new Chart(riskCtx, {
            type: 'doughnut',
            data: {
                labels: riskLabels,
                datasets: [{
                    data: riskValues,
                    backgroundColor: ['#22c55e', '#eab308', '#ef4444', '#94a3b8'],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });
    }
});
</script>
@endpush
@endsection
