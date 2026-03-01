@extends('layouts.admin')

@section('title', 'User Details')
@section('page-title', 'User Details')

@section('content')
<div class="admin-hub admin-user-detail-page">
    <header class="admin-header fade-in-element">
        <div class="admin-header-card">
            <div class="admin-header-main">
                <a href="{{ route('admin.users.index') }}" class="admin-back-link">
                    <i data-lucide="arrow-left" class="lucide-icon lucide-icon-sm" aria-hidden="true"></i>
                    Back to users
                </a>
                <div>
                    <h2 class="admin-header-title">{{ $user->name }}</h2>
                    <p class="admin-header-meta">{{ $user->email }}</p>
                </div>
                <div>
                    @if($user->is_admin)
                    <span class="admin-users-badge admin-users-badge-admin">Admin</span>
                    @else
                    <span class="admin-users-badge admin-users-badge-user">User</span>
                    @endif
                </div>
            </div>
        </div>
    </header>

    <section class="admin-section admin-user-detail-section fade-in-element">
        <h3 class="admin-section-title">Profile</h3>
        <div class="admin-detail-card">
            <dl class="admin-detail-list">
                <div class="admin-detail-row">
                    <dt>Name</dt>
                    <dd>{{ $user->name }}</dd>
                </div>
                <div class="admin-detail-row">
                    <dt>Email</dt>
                    <dd>{{ $user->email }}</dd>
                </div>
                <div class="admin-detail-row">
                    <dt>Role</dt>
                    <dd>{{ $user->is_admin ? 'Administrator' : 'User' }}</dd>
                </div>
                <div class="admin-detail-row">
                    <dt>Location</dt>
                    <dd>
                        @if($user->barangay || $user->municipality || $user->province)
                        {{ collect([$user->barangay, $user->municipality, $user->province])->filter()->implode(' → ') }}
                        @else
                        —
                        @endif
                    </dd>
                </div>
                <div class="admin-detail-row">
                    <dt>Joined</dt>
                    <dd>{{ $user->created_at->format('F j, Y \a\t g:i A') }}</dd>
                </div>
                @if($user->last_login_at)
                <div class="admin-detail-row">
                    <dt>Last login</dt>
                    <dd>{{ $user->last_login_at->format('F j, Y \a\t g:i A') }}</dd>
                </div>
                @endif
            </dl>
        </div>
    </section>

    <section class="admin-section admin-user-activity-section fade-in-element">
        <h3 class="admin-section-title">Activity</h3>
        <div class="admin-stats-grid admin-stats-grid-3">
            <article class="admin-stat-card">
                <i data-lucide="calculator" class="lucide-icon admin-stat-icon" aria-hidden="true"></i>
                <span class="admin-stat-value">{{ $user->calculation_histories_count ?? 0 }}</span>
                <span class="admin-stat-label">Calculations</span>
            </article>
            <article class="admin-stat-card">
                <i data-lucide="layers" class="lucide-icon admin-stat-icon" aria-hidden="true"></i>
                <span class="admin-stat-value">{{ $user->soil_loss_records_count ?? 0 }}</span>
                <span class="admin-stat-label">Soil Loss Records</span>
            </article>
            <article class="admin-stat-card">
                <i data-lucide="box" class="lucide-icon admin-stat-icon" aria-hidden="true"></i>
                <span class="admin-stat-value">{{ $user->regression_models_count ?? 0 }}</span>
                <span class="admin-stat-label">Regression Models</span>
            </article>
        </div>
    </section>
</div>
@endsection
