<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'TOCSEA') - Topographical Changes from Coastal Soil Erosion</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/landing.css', 'resources/css/login.css', 'resources/js/landing.js', 'resources/js/login.js'])
</head>
<body class="auth-page">
    {{-- Top Navigation Bar --}}
    <header class="top-nav">
        <div class="top-nav-inner container">
            <a href="{{ url('/') }}" class="top-nav-brand" aria-label="TOCSEA Home">TOCSEA</a>
            <nav class="top-nav-actions" aria-label="Account">
                @yield('nav-actions')
            </nav>
        </div>
    </header>

    <main class="auth-main">
        @yield('content')
    </main>

    {{-- Footer --}}
    <footer class="footer" id="contact">
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
</body>
</html>
