<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Admin') - TOCSEA</title>
    <meta name="description" content="TOCSEA Admin - System administration">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(array_merge(
        ['resources/css/landing.css', 'resources/views/admin/css/dashboard.css', 'resources/views/admin/js/dashboard.js'],
        request()->routeIs('admin.users.*') ? ['resources/views/admin/css/users.css', 'resources/views/admin/css/pagination.css', 'resources/views/admin/js/pagination.js', 'resources/views/admin/js/users.js'] : [],
        request()->routeIs('admin.models.*') ? ['resources/views/admin/css/users.css', 'resources/views/admin/css/models.css', 'resources/views/admin/css/pagination.css', 'resources/views/admin/js/pagination.js', 'resources/views/admin/js/models.js'] : [],
        request()->routeIs('admin.settings.*') ? ['resources/views/admin/css/settings.css', 'resources/views/admin/js/settings.js'] : [],
        request()->routeIs('admin.calculations.*') ? ['resources/views/admin/css/users.css', 'resources/views/admin/css/calculations.css', 'resources/views/admin/css/pagination.css', 'resources/views/admin/js/pagination.js', 'resources/views/admin/js/calculations.js'] : [],
        request()->routeIs('admin.analytics.*') ? ['resources/views/admin/css/analytics.css'] : []
    ))
    @stack('styles')
</head>
<body class="admin-page" data-auth-user-id="{{ auth()->id() }}">
    <aside class="admin-sidebar" id="adminSidebar" aria-label="Admin navigation">
        <div class="admin-sidebar-header">
            <a href="{{ route('admin.dashboard') }}" class="admin-sidebar-brand" aria-label="TOCSEA Admin">TOCSEA</a>
            <span class="admin-sidebar-badge">Admin</span>
        </div>
        <nav class="admin-sidebar-nav" aria-label="Admin sections">
            <a href="{{ route('admin.dashboard') }}" class="admin-sidebar-link {{ request()->routeIs('admin.dashboard') ? 'is-active' : '' }}">
                <i data-lucide="layout-dashboard" class="lucide-icon lucide-icon-nav" aria-hidden="true"></i>
                <span>Dashboard</span>
            </a>
            <a href="{{ route('admin.users.index') }}" class="admin-sidebar-link {{ request()->routeIs('admin.users.*') ? 'is-active' : '' }}">
                <i data-lucide="users" class="lucide-icon lucide-icon-nav" aria-hidden="true"></i>
                <span>User Management</span>
            </a>
            <a href="{{ route('admin.models.index') }}" class="admin-sidebar-link {{ request()->routeIs('admin.models.*') ? 'is-active' : '' }}">
                <i data-lucide="layers" class="lucide-icon lucide-icon-nav" aria-hidden="true"></i>
                <span>Model Management</span>
            </a>
            <a href="{{ route('admin.calculations.index') }}" class="admin-sidebar-link {{ request()->routeIs('admin.calculations.*') ? 'is-active' : '' }}">
                <i data-lucide="calculator" class="lucide-icon lucide-icon-nav" aria-hidden="true"></i>
                <span>Calculation Monitoring</span>
            </a>
            <a href="{{ route('admin.analytics.index') }}" class="admin-sidebar-link {{ request()->routeIs('admin.analytics.*') ? 'is-active' : '' }}">
                <i data-lucide="bar-chart-2" class="lucide-icon lucide-icon-nav" aria-hidden="true"></i>
                <span>Analytics</span>
            </a>
            <a href="{{ route('admin.settings.index') }}" class="admin-sidebar-link {{ request()->routeIs('admin.settings.*') ? 'is-active' : '' }}">
                <i data-lucide="settings" class="lucide-icon lucide-icon-nav" aria-hidden="true"></i>
                <span>Settings</span>
            </a>
        </nav>
        <div class="admin-sidebar-footer">
            <div class="admin-sidebar-user">
                <i data-lucide="user" class="lucide-icon lucide-icon-sm" aria-hidden="true"></i>
                <span>{{ auth()->user()->name }}</span>
            </div>
            <form method="POST" action="{{ url('/logout') }}">
                @csrf
                <button type="submit" class="admin-sidebar-logout" aria-label="Logout">
                    <i data-lucide="log-out" class="lucide-icon lucide-icon-sm" aria-hidden="true"></i>
                    <span>Logout</span>
                </button>
            </form>
        </div>
    </aside>

    <div class="admin-main-wrap">
        <header class="admin-topbar">
            <button type="button" class="admin-sidebar-toggle" id="adminSidebarToggle" aria-label="Toggle sidebar" aria-expanded="true">
                <i data-lucide="panel-left" class="lucide-icon lucide-icon-md" aria-hidden="true"></i>
            </button>
            <h1 class="admin-topbar-title">@yield('page-title', 'Admin')</h1>
        </header>

        <main class="admin-main">
            @yield('content')
        </main>
    </div>
    @stack('scripts')
</body>
</html>
