@extends('layouts.admin')

@section('title', 'Admin Dashboard')
@section('page-title', 'Dashboard')

@section('content')
<div class="admin-hub">
    <header class="admin-header fade-in-element">
        <div class="admin-header-card">
            <div class="admin-header-main">
                <div>
                    <h2 class="admin-header-title">Welcome, <span class="admin-header-name">{{ $user->name }}</span></h2>
                    <p class="admin-header-meta">
                        <i data-lucide="shield" class="lucide-icon lucide-icon-sm" aria-hidden="true"></i>
                        Full system privileges
                    </p>
                </div>
                <div class="admin-header-badge">
                    <i data-lucide="activity" class="lucide-icon lucide-icon-sm" aria-hidden="true"></i>
                    <span>Administrator</span>
                </div>
            </div>
        </div>
    </header>

    <section class="admin-section admin-stats-section fade-in-element">
        <h3 class="admin-section-title"><i data-lucide="bar-chart-2" class="lucide-icon lucide-icon-md" aria-hidden="true"></i> System Overview</h3>
        <div class="admin-stats-grid">
            <article class="admin-stat-card">
                <i data-lucide="users" class="lucide-icon admin-stat-icon" aria-hidden="true"></i>
                <span class="admin-stat-value">{{ $stats['totalUsers'] }}</span>
                <span class="admin-stat-label">Total Users</span>
            </article>
            <article class="admin-stat-card">
                <i data-lucide="shield" class="lucide-icon admin-stat-icon" aria-hidden="true"></i>
                <span class="admin-stat-value">{{ $stats['adminUsers'] }}</span>
                <span class="admin-stat-label">Admins</span>
            </article>
            <article class="admin-stat-card">
                <i data-lucide="calculator" class="lucide-icon admin-stat-icon" aria-hidden="true"></i>
                <span class="admin-stat-value">{{ $stats['totalCalculations'] }}</span>
                <span class="admin-stat-label">Total Calculations</span>
            </article>
            <article class="admin-stat-card">
                <i data-lucide="calendar" class="lucide-icon admin-stat-icon" aria-hidden="true"></i>
                <span class="admin-stat-value">{{ $stats['calculationsThisMonth'] }}</span>
                <span class="admin-stat-label">This Month</span>
            </article>
            <article class="admin-stat-card">
                <i data-lucide="box" class="lucide-icon admin-stat-icon" aria-hidden="true"></i>
                <span class="admin-stat-value">{{ $stats['savedModels'] }}</span>
                <span class="admin-stat-label">Saved Models</span>
            </article>
            <article class="admin-stat-card">
                <i data-lucide="user-plus" class="lucide-icon admin-stat-icon" aria-hidden="true"></i>
                <span class="admin-stat-value">{{ $stats['newUsersThisMonth'] }}</span>
                <span class="admin-stat-label">New Users (Month)</span>
            </article>
        </div>
    </section>

    <section class="admin-section admin-recent-section fade-in-element">
        <h3 class="admin-section-title"><i data-lucide="clock" class="lucide-icon lucide-icon-md" aria-hidden="true"></i> Recent Users</h3>
        <div class="admin-recent-card">
            @if($recentUsers->isEmpty())
                <div class="admin-recent-empty">
                    <i data-lucide="inbox" class="lucide-icon lucide-icon-lg" aria-hidden="true"></i>
                    <p>No users yet</p>
                </div>
            @else
                <ul class="admin-recent-list">
                    @foreach($recentUsers as $u)
                    <li class="admin-recent-item">
                        <div class="admin-recent-item-main">
                            <span class="admin-recent-item-name">{{ $u->name }}</span>
                            <span class="admin-recent-item-email">{{ $u->email }}</span>
                        </div>
                        @if($u->is_admin)
                        <span class="admin-recent-item-badge">Admin</span>
                        @endif
                        <a href="{{ route('admin.users.index') }}?search={{ urlencode($u->email) }}" class="admin-recent-item-link" aria-label="View {{ $u->name }}">
                            <i data-lucide="eye" class="lucide-icon lucide-icon-sm" aria-hidden="true"></i>
                            View
                        </a>
                    </li>
                    @endforeach
                </ul>
                <a href="{{ route('admin.users.index') }}" class="admin-recent-more">View all users →</a>
            @endif
        </div>
    </section>
</div>
@endsection
