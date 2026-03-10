<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Dashboard') - TOCSEA</title>
    <meta name="description" content="TOCSEA Dashboard - Coastal soil erosion assessment and weather">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(array_merge(
        ['resources/css/landing.css', 'resources/views/user/css/dashboard.css', 'resources/views/user/js/dashboard.js'],
        request()->routeIs('settings.*') ? ['resources/views/user/css/settings.css', 'resources/views/user/js/settings.js'] : []
    ))
    @stack('styles')
</head>
<body class="dashboard-page">
    <header class="top-nav">
        <div class="top-nav-inner container">
            <a href="{{ route('dashboard') }}" class="top-nav-brand" aria-label="TOCSEA Dashboard">TOCSEA</a>
            <button class="top-nav-menu-toggle" type="button" aria-label="Toggle navigation" aria-expanded="false" aria-controls="top-nav-mobile-menu">
                <span class="top-nav-menu-icon" aria-hidden="true"></span>
            </button>
            <nav class="top-nav-tools" aria-label="Tools">
                <a href="{{ route('dashboard') }}" class="top-nav-tool {{ request()->routeIs('dashboard') ? 'is-active' : '' }}" data-action="dashboard" aria-label="Dashboard">
                    <i data-lucide="layout-dashboard" class="lucide-icon lucide-icon-nav" aria-hidden="true"></i>
                    <span class="top-nav-tool-label">Dashboard</span>
                </a>
                <a href="{{ route('soil-calculator') }}" class="top-nav-tool {{ request()->routeIs('soil-calculator') ? 'is-active' : '' }}" data-action="calculator" aria-label="Soil Calculator">
                    <i data-lucide="calculator" class="lucide-icon lucide-icon-nav" aria-hidden="true"></i>
                    <span class="top-nav-tool-label">Soil Calculator</span>
                </a>
                <a href="{{ route('model-builder') }}" class="top-nav-tool {{ request()->routeIs('model-builder') || request()->routeIs('model-builder.saved-equations') ? 'is-active' : '' }}" data-action="model-builder" aria-label="Model Builder">
                    <i data-lucide="box" class="lucide-icon lucide-icon-nav" aria-hidden="true"></i>
                    <span class="top-nav-tool-label">Model Builder</span>
                </a>
                <a href="{{ route('calculation-history.index') }}" class="top-nav-tool {{ request()->routeIs('calculation-history.*') ? 'is-active' : '' }}" data-action="calculation-history" aria-label="Calculation History">
                    <i data-lucide="history" class="lucide-icon lucide-icon-nav" aria-hidden="true"></i>
                    <span class="top-nav-tool-label">Calculation History</span>
                </a>
                <a href="{{ route('ask-tocsea') }}" class="top-nav-tool {{ request()->routeIs('ask-tocsea') ? 'is-active' : '' }}" data-action="ask-tocsea" aria-label="Ask TOCSEA">
                    <i data-lucide="message-circle" class="lucide-icon lucide-icon-nav" aria-hidden="true"></i>
                    <span class="top-nav-tool-label">Ask TOCSEA</span>
                </a>
            </nav>
            <nav class="top-nav-actions" aria-label="Account">
                <div class="top-nav-user-menu">
                    <button type="button" class="top-nav-user-trigger" aria-expanded="false" aria-controls="top-nav-user-dropdown" aria-haspopup="true" aria-label="Account menu">
                        <i data-lucide="user" class="lucide-icon lucide-icon-sm top-nav-user-icon" aria-hidden="true"></i>
                        <span class="top-nav-user-name">{{ auth()->user()->name }}</span>
                        <i data-lucide="chevron-down" class="lucide-icon lucide-icon-sm top-nav-user-chevron" aria-hidden="true"></i>
                    </button>
                    <div class="top-nav-user-dropdown" id="top-nav-user-dropdown" role="menu" aria-hidden="true">
                        @if(auth()->user()->isAdmin())
                        <a href="{{ route('admin.dashboard') }}" class="top-nav-dropdown-item" role="menuitem">
                            <i data-lucide="shield" class="lucide-icon lucide-icon-sm" aria-hidden="true"></i>
                            <span>Admin</span>
                        </a>
                        @endif
                        <a href="{{ route('settings.index') }}" class="top-nav-dropdown-item {{ request()->routeIs('settings.*') ? 'is-active' : '' }}" role="menuitem" data-action="settings">
                            <i data-lucide="settings" class="lucide-icon lucide-icon-sm" aria-hidden="true"></i>
                            <span>Settings</span>
                        </a>
                        <form id="logout-form" method="POST" action="{{ url('/logout') }}" class="top-nav-form top-nav-dropdown-item-wrap">
                            @csrf
                            <button type="submit" class="top-nav-dropdown-item top-nav-dropdown-item-logout" role="menuitem">
                                <i data-lucide="log-out" class="lucide-icon lucide-icon-sm" aria-hidden="true"></i>
                                <span>Logout</span>
                            </button>
                        </form>
                    </div>
                </div>
            </nav>
        </div>
        <div class="top-nav-mobile-menu" id="top-nav-mobile-menu" aria-label="Mobile navigation" aria-hidden="true">
            <nav class="top-nav-mobile-nav">
                <a href="{{ route('dashboard') }}" class="top-nav-mobile-link {{ request()->routeIs('dashboard') ? 'is-active' : '' }}">Dashboard</a>
                <a href="{{ route('soil-calculator') }}" class="top-nav-mobile-link {{ request()->routeIs('soil-calculator') ? 'is-active' : '' }}">Soil Calculator</a>
                <a href="{{ route('model-builder') }}" class="top-nav-mobile-link {{ request()->routeIs('model-builder') || request()->routeIs('model-builder.saved-equations') ? 'is-active' : '' }}">Model Builder</a>
                <a href="{{ route('calculation-history.index') }}" class="top-nav-mobile-link {{ request()->routeIs('calculation-history.*') ? 'is-active' : '' }}">Summary / Reports</a>
                <a href="{{ route('ask-tocsea') }}" class="top-nav-mobile-link {{ request()->routeIs('ask-tocsea') ? 'is-active' : '' }}">Ask TOCSEA</a>
                <a href="{{ route('settings.index') }}" class="top-nav-mobile-link {{ request()->routeIs('settings.*') ? 'is-active' : '' }}">Settings</a>
                <button type="button" class="top-nav-mobile-link top-nav-mobile-link-logout" data-mobile-logout="true">
                    Logout
                </button>
            </nav>
        </div>
    </header>

    <main class="dashboard-main">
        @yield('content')
    </main>

    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <p class="footer-name">TOCSEA</p>
                <p class="footer-tagline">Predictive Coastal Soil Analysis</p>
                <a href="mailto:info@tocsea.com" class="footer-email">info@tocsea.com</a>
            </div>
            <div class="footer-divider" aria-hidden="true"></div>
            <p class="footer-copyright">&copy; {{ date('Y') }} TOCSEA. All rights reserved.</p>
        </div>
    </footer>
    @stack('scripts')
</body>
</html>
