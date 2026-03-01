@extends('layouts.dashboard')

@section('title', 'Dashboard')

@section('content')
<div class="dashboard-hub">
    {{-- 1️⃣ Header / User Info --}}
    <header class="dashboard-header fade-in-element">
        <div class="header-card">
            <div class="header-main">
                <div>
                    <h1 class="header-title">Welcome back, <span class="header-name">{{ $user->name }}</span></h1>
                    <p class="header-location">
                        <i data-lucide="map-pin" class="lucide-icon lucide-icon-sm" aria-hidden="true"></i>
                        {{ $location['barangay'] }} → {{ $location['municipality'] }} → {{ $location['province'] }}
                    </p>
                    @if(isset($lastLogin))
                    <p class="header-meta header-meta-with-icon">
                        <i data-lucide="calendar" class="lucide-icon lucide-icon-sm" aria-hidden="true"></i>
                        Last login: {{ $lastLogin }}
                    </p>
                    @endif
                </div>
                <div class="header-badge">
                    <i data-lucide="activity" class="lucide-icon lucide-icon-sm" aria-hidden="true"></i>
                    <span>Coastal Manager</span>
                </div>
            </div>
        </div>
    </header>

    {{-- 2️⃣ Weather --}}
    <section class="dashboard-section dashboard-weather-section fade-in-element">
        <h2 class="section-title"><i data-lucide="cloud" class="lucide-icon lucide-icon-md" aria-hidden="true"></i> Weather & Environment</h2>
        <div class="weather-grid">
            {{-- Today: large highlighted card --}}
            <article class="weather-card weather-card-today weather-card-today-premium" id="weatherTodayCard">
                <div class="weather-card-loading" id="weatherTodayLoading">
                    <span class="loading-spinner"></span>
                    <span>Loading…</span>
                </div>
                <div class="weather-card-content" id="weatherTodayContent" hidden>
                    <div class="weather-today-top">
                        <div class="weather-today-meta">
                            <span class="weather-location" id="weatherLocation">—</span>
                            <span class="weather-date" id="weatherDate">—</span>
                        </div>
                        <div class="weather-temp-toggle" role="group" aria-label="Temperature unit">
                            <button type="button" class="weather-unit-btn is-active" data-unit="c" aria-pressed="true">°C</button>
                            <button type="button" class="weather-unit-btn" data-unit="f" aria-pressed="false">°F</button>
                        </div>
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
                    <div class="weather-today-stats">
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
                    <p class="weather-insight" id="weatherInsight"></p>
                    <p class="weather-last-updated" id="weatherLastUpdated"></p>
                </div>
                <div class="weather-card-error" id="weatherTodayError" hidden>
                    <i data-lucide="alert-triangle" class="lucide-icon lucide-icon-xl" aria-hidden="true"></i>
                    <p id="weatherErrorText">Unable to load weather</p>
                </div>
            </article>
            {{-- 5-Day Forecast: premium container same style as Today --}}
            <article class="weather-card weather-card-forecast weather-card-forecast-premium" id="weatherForecastCard">
                <div class="weather-card-loading" id="weatherForecastLoading">
                    <span class="loading-spinner"></span>
                    <span>Loading…</span>
                </div>
                <div class="weather-card-content" id="weatherForecastContent" hidden>
                    <h3 class="forecast-premium-title">5-Day Forecast</h3>
                    <div class="forecast-rows" id="forecastDays"></div>
                </div>
                <div class="weather-card-error" id="weatherForecastError" hidden>
                    <i data-lucide="alert-triangle" class="lucide-icon lucide-icon-xl" aria-hidden="true"></i>
                    <p>Unable to load forecast</p>
                </div>
            </article>
        </div>
    </section>

    {{-- 3️⃣ Summary Overview --}}
    <section class="dashboard-section dashboard-summary-section fade-in-element">
        <h2 class="section-title"><i data-lucide="bar-chart-2" class="lucide-icon lucide-icon-md" aria-hidden="true"></i> Summary Overview</h2>
        <div class="summary-card">
            <div class="summary-row">
                <i data-lucide="calculator" class="lucide-icon summary-icon" aria-hidden="true"></i>
                <span class="summary-label">Total Calculations (This Month)</span>
                <span class="summary-value">{{ $summaryOverview['totalCalculationsThisMonth'] }}</span>
            </div>
            <div class="summary-row">
                <i data-lucide="box" class="lucide-icon summary-icon" aria-hidden="true"></i>
                <span class="summary-label">Saved Models</span>
                <span class="summary-value">{{ $summaryOverview['savedModels'] }}</span>
            </div>
            <div class="summary-row">
                <i data-lucide="activity" class="lucide-icon summary-icon" aria-hidden="true"></i>
                <span class="summary-label">Latest Soil Loss Result</span>
                <span class="summary-value">{{ $summaryOverview['latestSoilLossResult'] !== null ? number_format($summaryOverview['latestSoilLossResult'], 2) . ' m²/year' : '—' }}</span>
            </div>
            <div class="summary-row summary-row-last">
                <i data-lucide="shield-alert" class="lucide-icon summary-icon" aria-hidden="true"></i>
                <span class="summary-label">Current Risk Level</span>
                @php $riskClass = $summaryOverview['currentRiskLevel'] === '—' ? 'na' : strtolower($summaryOverview['currentRiskLevel']); @endphp
                <span class="summary-value summary-value-risk summary-value-risk-{{ $riskClass }}">{{ $summaryOverview['currentRiskLevel'] }}</span>
            </div>
        </div>
    </section>

    {{-- 4️⃣ Recent Activity --}}
    <section class="dashboard-section dashboard-recent-activity-section fade-in-element">
        <h2 class="section-title"><i data-lucide="clock" class="lucide-icon lucide-icon-md" aria-hidden="true"></i> Recent Activity</h2>
        <div class="recent-activity-card">
            @if($recentCalculations->isEmpty())
                <div class="recent-activity-empty">
                    <i data-lucide="inbox" class="lucide-icon lucide-icon-lg" aria-hidden="true"></i>
                    <p>No calculations yet</p>
                    <a href="{{ route('soil-calculator') }}" class="recent-activity-empty-link">Go to Soil Calculator</a>
                </div>
            @else
                <ul class="recent-activity-list">
                    @foreach($recentCalculations as $calc)
                    <li class="recent-activity-item">
                        <span class="recent-activity-date">{{ $calc->created_at->format('M j, Y g:i A') }}</span>
                        <span class="recent-activity-result">
                            <span class="recent-activity-result-value">{{ number_format($calc->result, 2) }}</span>
                            <span class="recent-activity-result-unit">m²/year</span>
                        </span>
                        <a href="{{ route('calculation-history.index') }}" class="dashboard-activity-view-btn" aria-label="View calculation history">
                            <i data-lucide="eye" class="lucide-icon lucide-icon-sm" aria-hidden="true"></i>
                            View
                        </a>
                    </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </section>

    {{-- 5️⃣ Quick Actions: mirrors topbar sections --}}
    <section class="dashboard-section dashboard-actions fade-in-element">
        <h2 class="section-title"><i data-lucide="zap" class="lucide-icon lucide-icon-md" aria-hidden="true"></i> Quick Actions</h2>
        <p class="section-subtitle">Jump to main tools</p>
        <div class="action-cards">
            <a href="{{ route('soil-calculator') }}" class="action-card {{ request()->routeIs('soil-calculator') ? 'is-active' : '' }}" data-action="calculator" aria-label="Soil Calculator">
                <i data-lucide="calculator" class="lucide-icon lucide-icon-lg" aria-hidden="true"></i>
                <span>Soil Calculator</span>
                <span class="action-card-desc">Predict soil loss</span>
            </a>
            <a href="{{ route('model-builder') }}" class="action-card {{ request()->routeIs('model-builder') ? 'is-active' : '' }}" data-action="model-builder" aria-label="Model Builder">
                <i data-lucide="box" class="lucide-icon lucide-icon-lg" aria-hidden="true"></i>
                <span>Model Builder</span>
                <span class="action-card-desc">Build regression models</span>
            </a>
            <a href="{{ route('calculation-history.index') }}" class="action-card {{ request()->routeIs('calculation-history.*') ? 'is-active' : '' }}" data-action="calculation-history" aria-label="Calculation History">
                <i data-lucide="history" class="lucide-icon lucide-icon-lg" aria-hidden="true"></i>
                <span>Calculation History</span>
                <span class="action-card-desc">Review past calculations</span>
            </a>
            <a href="#ask-tocsea" class="action-card" data-action="ask-tocsea" aria-label="Ask TOCSEA">
                <i data-lucide="message-circle" class="lucide-icon lucide-icon-lg" aria-hidden="true"></i>
                <span>Ask TOCSEA</span>
                <span class="action-card-desc">Get answers & guidance</span>
            </a>
        </div>
    </section>
</div>
@endsection
