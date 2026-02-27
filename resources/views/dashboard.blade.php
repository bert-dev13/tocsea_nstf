@extends('layouts.dashboard')

@section('title', 'Dashboard')

@section('content')
<div class="dashboard-hub">
    {{-- 1. Enhanced Header / Welcome Section --}}
    <header class="dashboard-header fade-in-element">
        <div class="header-card">
            <div class="header-main">
                <div class="header-left">
                    <div class="header-top-row">
                        <h1 class="header-title">Welcome back, <span class="header-name">{{ $user->name }}</span></h1>
                        <span class="header-badge">
                            <i data-lucide="shield-check" class="lucide-icon lucide-icon-sm" aria-hidden="true"></i>
                            Coastal Manager
                        </span>
                    </div>
                    <p class="header-location">
                        <i data-lucide="map-pin" class="lucide-icon lucide-icon-sm" aria-hidden="true"></i>
                        {{ $location['barangay'] }} → {{ $location['municipality'] }} → {{ $location['province'] }}
                    </p>
                    @if(isset($lastLogin))
                    <p class="header-meta header-meta-with-icon">
                        <i data-lucide="clock" class="lucide-icon lucide-icon-sm" aria-hidden="true"></i>
                        Last login: {{ $lastLogin }}
                    </p>
                    @endif
                </div>
                <div class="header-right">
                    <div class="system-status">
                        <span class="system-status-dot" aria-hidden="true"></span>
                        <span class="system-status-text">All systems operational</span>
                    </div>
                </div>
            </div>
        </div>
    </header>

    {{-- 2. Coastal Risk Overview - Stat Cards --}}
    <section class="dashboard-section dashboard-stats fade-in-element">
        <h2 class="section-header">
            <i data-lucide="bar-chart-2" class="section-header-icon" aria-hidden="true"></i>
            Coastal Risk Overview
        </h2>
        <div class="stats-grid">
            <div class="stat-card">
                <i data-lucide="calculator" class="stat-card-icon" aria-hidden="true"></i>
                <div class="stat-card-body">
                    <span class="stat-card-value">{{ $stats['calculationsThisMonth'] }}</span>
                    <span class="stat-card-label">Calculations This Month</span>
                    @if(isset($stats['calculationsTrend']))
                    <span class="stat-card-trend stat-trend-{{ $stats['calculationsTrend']['direction'] }}">
                        {{ $stats['calculationsTrend']['change'] >= 0 ? '↑' : '↓' }} {{ abs($stats['calculationsTrend']['change']) }}%
                    </span>
                    @endif
                </div>
            </div>
            <div class="stat-card">
                <i data-lucide="layers" class="stat-card-icon" aria-hidden="true"></i>
                <div class="stat-card-body">
                    <span class="stat-card-value">{{ $stats['activeModels'] }}</span>
                    <span class="stat-card-label">Active Models Saved</span>
                </div>
            </div>
            <div class="stat-card">
                <i data-lucide="trending-down" class="stat-card-icon" aria-hidden="true"></i>
                <div class="stat-card-body">
                    <span class="stat-card-value">{{ $stats['avgSoilLossMonthly'] ?? '—' }}{{ $stats['avgSoilLossMonthly'] !== null ? ' t/ha' : '' }}</span>
                    <span class="stat-card-label">Avg Predicted Soil Loss (Monthly)</span>
                    @if(isset($stats['avgSoilLossTrend']))
                    <span class="stat-card-trend stat-trend-{{ $stats['avgSoilLossTrend']['direction'] }}">
                        {{ $stats['avgSoilLossTrend']['change'] >= 0 ? '↑' : '↓' }} {{ abs($stats['avgSoilLossTrend']['change']) }}%
                    </span>
                    @endif
                </div>
            </div>
            <div class="stat-card">
                <i data-lucide="alert-triangle" class="stat-card-icon" aria-hidden="true"></i>
                <div class="stat-card-body">
                    <span class="stat-card-value">{{ $stats['highRiskEvents'] }}</span>
                    <span class="stat-card-label">High-Risk Events Logged</span>
                </div>
            </div>
            @if($stats['vegetationTrend'] ?? null)
            <div class="stat-card">
                <i data-lucide="leaf" class="stat-card-icon" aria-hidden="true"></i>
                <div class="stat-card-body">
                    <span class="stat-card-value">{{ $stats['vegetationTrend']['label'] }}</span>
                    <span class="stat-card-label">Soil Loss Trend (Annual)</span>
                </div>
            </div>
            @endif
        </div>
    </section>

    {{-- 3. Main Content Grid: Weather + Risk Meter + Recent Activity --}}
    <div class="dashboard-main-grid">
        {{-- Weather & Environment (Enhanced) --}}
        <section class="dashboard-section dashboard-weather-section fade-in-element">
            <h2 class="section-header">
                <i data-lucide="cloud" class="section-header-icon" aria-hidden="true"></i>
                Weather & Environment
            </h2>
            <div class="weather-grid">
                <article class="weather-card weather-card-today weather-card-today-premium" id="weatherTodayCard">
                    <div class="weather-card-loading" id="weatherTodayLoading">
                        <span class="loading-spinner"></span>
                        <span>Loading…</span>
                    </div>
                    <div class="weather-card-content" id="weatherTodayContent" hidden>
                        <div class="weather-today-layout">
                            <div class="weather-today-left">
                                <div class="weather-today-top">
                                    <span class="weather-location" id="weatherLocation">—</span>
                                    <span class="weather-date" id="weatherDate">—</span>
                                </div>
                                <div class="weather-temp-toggle" role="group" aria-label="Temperature unit">
                                    <button type="button" class="weather-unit-btn is-active" data-unit="c" aria-pressed="true">°C</button>
                                    <button type="button" class="weather-unit-btn" data-unit="f" aria-pressed="false">°F</button>
                                </div>
                                <div class="weather-today-hero">
                                    <div class="weather-icon-wrap weather-icon-main" id="weatherIconWrap">
                                        <i data-lucide="sun" class="lucide-icon weather-icon-lucide" id="weatherIconToday" aria-hidden="true"></i>
                                    </div>
                                    <div class="weather-temp-display">
                                        <span class="weather-temp-value" id="weatherTempToday">—</span>
                                        <span class="weather-temp-unit" id="weatherTempUnit">°C</span>
                                    </div>
                                    <p class="weather-condition" id="weatherConditionToday">—</p>
                                    <p class="weather-feels-like">Feels like <span id="weatherFeelsLike">—</span></p>
                                </div>
                            </div>
                            <div class="weather-today-right">
                                <div class="weather-metrics-grid">
                                    <div class="weather-stat">
                                        <i data-lucide="droplets" class="lucide-icon weather-stat-icon" aria-hidden="true"></i>
                                        <span class="weather-stat-value" id="weatherHumidity">—</span>
                                        <span class="weather-stat-label">Humidity</span>
                                    </div>
                                    <div class="weather-stat">
                                        <i data-lucide="wind" class="lucide-icon weather-stat-icon" aria-hidden="true"></i>
                                        <span class="weather-stat-value" id="weatherWind">—</span>
                                        <span class="weather-stat-label">Wind</span>
                                    </div>
                                    <div class="weather-stat">
                                        <i data-lucide="gauge" class="lucide-icon weather-stat-icon" aria-hidden="true"></i>
                                        <span class="weather-stat-value" id="weatherPressure">—</span>
                                        <span class="weather-stat-label">Pressure</span>
                                    </div>
                                    <div class="weather-stat">
                                        <i data-lucide="sun" class="lucide-icon weather-stat-icon" aria-hidden="true"></i>
                                        <span class="weather-stat-value" id="weatherSunTimes">—</span>
                                        <span class="weather-stat-label">Sun</span>
                                    </div>
                                </div>
                                <div class="weather-extras">
                                    <div class="weather-extra-item">
                                        <i data-lucide="waves" class="lucide-icon" aria-hidden="true"></i>
                                        <span>Tidal: <strong>—</strong></span>
                                    </div>
                                    <div class="weather-extra-item weather-rain-bar-wrap">
                                        <i data-lucide="cloud-rain" class="lucide-icon" aria-hidden="true"></i>
                                        <div class="weather-rain-bar" id="weatherRainBar"><span class="weather-rain-fill" id="weatherRainFill"></span></div>
                                        <span class="weather-rain-label" id="weatherRainLabel">—% rain</span>
                                    </div>
                                    <div class="weather-extra-item">
                                        <i data-lucide="flower" class="lucide-icon" aria-hidden="true"></i>
                                        <span>Soil moisture: <strong>—</strong></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <p class="weather-insight" id="weatherInsight"></p>
                        <p class="weather-last-updated" id="weatherLastUpdated"></p>
                    </div>
                    <div class="weather-card-error" id="weatherTodayError" hidden>
                        <i data-lucide="alert-triangle" class="lucide-icon lucide-icon-xl" aria-hidden="true"></i>
                        <p id="weatherErrorText">Unable to load weather</p>
                    </div>
                </article>
                <article class="weather-card weather-card-forecast weather-card-forecast-premium" id="weatherForecastCard">
                    <div class="weather-card-loading" id="weatherForecastLoading">
                        <span class="loading-spinner"></span>
                        <span>Loading…</span>
                    </div>
                    <div class="weather-card-content" id="weatherForecastContent" hidden>
                        <h3 class="forecast-premium-title">5-Day Forecast</h3>
                        <div class="forecast-rows forecast-rows-scroll" id="forecastDays"></div>
                    </div>
                    <div class="weather-card-error" id="weatherForecastError" hidden>
                        <i data-lucide="alert-triangle" class="lucide-icon lucide-icon-xl" aria-hidden="true"></i>
                        <p>Unable to load forecast</p>
                    </div>
                </article>
            </div>
        </section>

        {{-- Risk Level Indicator + Recent Activity Column --}}
        <aside class="dashboard-sidebar">
            {{-- Risk Level Widget --}}
            <section class="dashboard-section risk-meter-section fade-in-element">
                <h2 class="section-header">
                    <i data-lucide="gauge" class="section-header-icon" aria-hidden="true"></i>
                    Soil Loss Risk Meter
                </h2>
                <div class="risk-meter-card">
                    <div class="risk-meter-bar-wrap">
                        <div class="risk-meter-bar">
                            <div class="risk-meter-fill risk-meter-{{ $riskLevel['category'] }}" style="width: {{ min(100, $riskLevel['percent']) }}%"></div>
                        </div>
                        <div class="risk-meter-markers">
                            <span>Low</span>
                            <span>Moderate</span>
                            <span>High</span>
                            <span>Critical</span>
                        </div>
                    </div>
                    <p class="risk-meter-value">
                        <strong>{{ $riskLevel['value'] > 0 ? number_format($riskLevel['value'], 1) . ' t/ha' : 'No data' }}</strong>
                        <span class="risk-meter-label">{{ $riskLevel['label'] }}</span>
                    </p>
                    <p class="risk-meter-hint">Based on latest calculation</p>
                </div>
            </section>

            {{-- Recent Activity --}}
            <section class="dashboard-section recent-activity-section fade-in-element">
                <h2 class="section-header">
                    <i data-lucide="activity" class="section-header-icon" aria-hidden="true"></i>
                    Recent Activity
                </h2>
                <div class="recent-activity-card">
                    @forelse($recentCalculations as $calc)
                    <a href="{{ route('calculation-history.index') }}" class="recent-activity-item" data-id="{{ $calc->id }}">
                        <div class="recent-activity-main">
                            <span class="recent-activity-title">{{ Str::limit($calc->equation_name, 28) }}</span>
                            <span class="recent-activity-result">{{ number_format((float) $calc->result, 2) }} t/ha</span>
                        </div>
                        <div class="recent-activity-meta">
                            <span class="recent-activity-time">{{ $calc->created_at->diffForHumans() }}</span>
                            <span class="recent-activity-view">View <i data-lucide="chevron-right" class="lucide-icon lucide-icon-sm" aria-hidden="true"></i></span>
                        </div>
                    </a>
                    @empty
                    <div class="recent-activity-empty">
                        <i data-lucide="inbox" class="lucide-icon lucide-icon-lg" aria-hidden="true"></i>
                        <p>No calculations yet</p>
                        <a href="{{ route('soil-calculator') }}" class="recent-activity-cta">Run first calculation</a>
                    </div>
                    @endforelse
                    @if($lastModelEdited)
                    <div class="recent-activity-footer">
                        <span class="recent-activity-footer-label">Last model edited:</span>
                        <span class="recent-activity-footer-value">{{ $lastModelEdited->equation_name }}</span>
                    </div>
                    @endif
                </div>
            </section>
        </aside>
    </div>

    {{-- 4. Environmental Insights Panel --}}
    <section class="dashboard-section insights-section fade-in-element">
        <h2 class="section-header">
            <i data-lucide="lightbulb" class="section-header-icon" aria-hidden="true"></i>
            Environmental Insights
        </h2>
        <div class="insights-grid">
            @foreach($insights as $insight)
            <div class="insight-card insight-card-{{ $insight['type'] }}">
                <i data-lucide="{{ $insight['type'] === 'warning' ? 'alert-triangle' : ($insight['type'] === 'positive' ? 'check-circle' : 'info') }}" class="insight-card-icon" aria-hidden="true"></i>
                <p class="insight-card-message">{{ $insight['message'] }}</p>
            </div>
            @endforeach
        </div>
    </section>

    {{-- 5. Performance Summary (Charts) --}}
    <section class="dashboard-section performance-section fade-in-element">
        <h2 class="section-header">
            <i data-lucide="trending-up" class="section-header-icon" aria-hidden="true"></i>
            Performance Summary
        </h2>
        <div class="charts-grid">
            <div class="chart-card">
                <h3 class="chart-title">Soil Loss Trend (Last 6 Months)</h3>
                <div class="chart-bar-wrap" id="chartSoilLoss" role="img" aria-label="Soil loss trend chart">
                    @foreach($soilLossTrend as $point)
                    <div class="chart-bar-item">
                        <div class="chart-bar" style="--bar-height: {{ $point['value'] > 0 ? min(100, ($point['value'] / max($soilLossMax, 1)) * 100) : 2 }}%"></div>
                        <span class="chart-bar-label">{{ $point['month'] }}</span>
                    </div>
                    @endforeach
                </div>
            </div>
            <div class="chart-card">
                <h3 class="chart-title">Storm Events Frequency</h3>
                <div class="chart-bar-wrap" id="chartStorm" role="img" aria-label="Storm events chart">
                    @foreach($stormFrequency as $point)
                    <div class="chart-bar-item">
                        <div class="chart-bar chart-bar-alt" style="--bar-height: {{ $point['value'] > 0 ? min(100, ($point['value'] / 5) * 100) : 2 }}%"></div>
                        <span class="chart-bar-label">{{ $point['month'] }}</span>
                    </div>
                    @endforeach
                </div>
            </div>
            <div class="chart-card">
                <h3 class="chart-title">Flood Incidence</h3>
                <div class="chart-bar-wrap" id="chartFlood" role="img" aria-label="Flood incidence chart">
                    @foreach($floodIncidence as $point)
                    <div class="chart-bar-item">
                        <div class="chart-bar chart-bar-flood" style="--bar-height: {{ $point['value'] > 0 ? min(100, ($point['value'] / 3) * 100) : 2 }}%"></div>
                        <span class="chart-bar-label">{{ $point['month'] }}</span>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    {{-- 6. Quick Actions (Improved 2x2 Grid) --}}
    <section class="dashboard-section dashboard-actions fade-in-element">
        <h2 class="section-header">
            <i data-lucide="zap" class="section-header-icon" aria-hidden="true"></i>
            Quick Actions
        </h2>
        <p class="section-subtitle">Jump to main tools</p>
        <div class="action-cards">
            <a href="{{ route('soil-calculator') }}" class="action-card {{ request()->routeIs('soil-calculator') ? 'is-active' : '' }}" data-action="calculator" aria-label="Soil Calculator">
                <i data-lucide="calculator" class="action-card-icon" aria-hidden="true"></i>
                <span class="action-card-title">Soil Calculator</span>
                <span class="action-card-desc">Predict soil loss and erosion risk</span>
            </a>
            <a href="{{ route('model-builder') }}" class="action-card {{ request()->routeIs('model-builder') ? 'is-active' : '' }}" data-action="model-builder" aria-label="Model Builder">
                <i data-lucide="box" class="action-card-icon" aria-hidden="true"></i>
                <span class="action-card-title">Model Builder</span>
                <span class="action-card-desc">Build regression models</span>
            </a>
            <a href="{{ route('calculation-history.index') }}" class="action-card {{ request()->routeIs('calculation-history.*') ? 'is-active' : '' }}" data-action="calculation-history" aria-label="Calculation History">
                <i data-lucide="history" class="action-card-icon" aria-hidden="true"></i>
                <span class="action-card-title">Calculation History</span>
                <span class="action-card-desc">Review past calculations</span>
            </a>
            <a href="#ask-tocsea" class="action-card" data-action="ask-tocsea" aria-label="Ask TOCSEA">
                <i data-lucide="message-circle" class="action-card-icon" aria-hidden="true"></i>
                <span class="action-card-title">Ask TOCSEA</span>
                <span class="action-card-desc">Get answers & guidance</span>
            </a>
        </div>
    </section>
</div>
@endsection
