<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TOCSEA - Topographical Changes from Coastal Soil Erosion</title>
    <meta name="description" content="Research-grade decision-support system for assessing coastal soil erosion risks and planning mitigation strategies">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/landing.css', 'resources/js/landing.js'])
</head>
<body>
    {{-- Top Navigation Bar --}}
    <header class="top-nav">
        <div class="top-nav-inner container">
            <a href="#" class="top-nav-brand" aria-label="TOCSEA Home">TOCSEA</a>
            <nav class="top-nav-actions" aria-label="Account">
                @auth
                    <a href="{{ route('dashboard') }}" class="top-nav-btn top-nav-btn-register"><i data-feather="layout" aria-hidden="true"></i><span>Dashboard</span></a>
                    <form method="POST" action="{{ url('/logout') }}" class="top-nav-form">
                        @csrf
                        <button type="submit" class="top-nav-btn top-nav-btn-login"><i data-feather="log-out" aria-hidden="true"></i><span>Logout</span></button>
                    </form>
                @else
                    <a href="{{ url('/login') }}" class="top-nav-btn top-nav-btn-login"><i data-feather="log-in" aria-hidden="true"></i><span>Login</span></a>
                    <a href="{{ url('/register') }}" class="top-nav-btn top-nav-btn-register"><i data-feather="user-plus" aria-hidden="true"></i><span>Register</span></a>
                @endauth
            </nav>
        </div>
    </header>

    <main>
        {{-- Hero Section --}}
        <section class="hero" id="hero">
            <div class="hero-bg"></div>
            <div class="container hero-inner">
                <div class="hero-content hero-grid">
                    <div class="hero-col hero-col-logo">
                        <img src="/images/tocsea-logo.png" alt="TOCSEA Logo" class="hero-logo" width="200" height="200">
                    </div>
                    <div class="hero-col hero-col-text">
                        <h1 class="hero-title">TOCSEA</h1>
                        <p class="hero-subtitle">Predicting Coastal Soil Loss Through Data and Analytics</p>
                        <p class="hero-description">
                            TOCSEA is a web-based predictive and analytical system that calculates annual soil loss, allows custom regression equation generation, and supports accurate coastal assessment and planning for researchers and coastal managers.
                        </p>
                        <div class="hero-actions">
                            <a href="#features" class="btn btn-primary btn-large hero-btn"><i data-feather="arrow-right" aria-hidden="true"></i><span>Get Started</span></a>
                            <a href="#features" class="btn btn-secondary btn-large hero-btn"><i data-feather="info" aria-hidden="true"></i><span>Learn More</span></a>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- About TOCSEA --}}
        <section id="about" class="section about-tocsea">
            <div class="container">
                <div class="section-header fade-in-element">
                    <h2>About TOCSEA</h2>
                </div>
                <div class="about-tocsea-cards">
                    <article class="about-card fade-in-element">
                        <div class="icon-wrap" aria-hidden="true"><i data-feather="bar-chart-2"></i></div>
                        <h3>Annual Soil Loss Calculation</h3>
                        <p>Compute precise annual soil erosion rates using validated predictive models and site-specific parameters.</p>
                    </article>
                    <article class="about-card fade-in-element">
                        <div class="icon-wrap" aria-hidden="true"><i data-feather="book-open"></i></div>
                        <h3>Regression Equation Generation</h3>
                        <p>Build and calibrate custom regression equations tailored to your coastal study areas and erosion drivers.</p>
                    </article>
                    <article class="about-card fade-in-element">
                        <div class="icon-wrap" aria-hidden="true"><i data-feather="layers"></i></div>
                        <h3>Model Management</h3>
                        <p>Organize, compare, and maintain multiple erosion models for different scenarios and time horizons.</p>
                    </article>
                    <article class="about-card fade-in-element">
                        <div class="icon-wrap" aria-hidden="true"><i data-feather="database"></i></div>
                        <h3>Coastal Data Integration</h3>
                        <p>Import and analyze topographical, soil, and environmental data for comprehensive coastal assessments.</p>
                    </article>
                </div>
            </div>
        </section>

        {{-- Core Features --}}
        <section id="features" class="section features">
            <div class="container">
                <div class="section-header fade-in-element">
                    <h2>Core Features</h2>
                    <p>Comprehensive tools for coastal erosion assessment and management</p>
                </div>
                <div class="features-grid">
                    <article class="feature-card fade-in-element">
                        <div class="icon-wrap icon-wrap--feature" aria-hidden="true"><i data-feather="shield"></i></div>
                        <h3>Risk Assessment</h3>
                        <p>Evaluate erosion risks with advanced topographical analysis and real-time data processing</p>
                    </article>
                    <article class="feature-card fade-in-element">
                        <div class="icon-wrap icon-wrap--feature" aria-hidden="true"><i data-feather="bar-chart-2"></i></div>
                        <h3>Soil Loss Estimation</h3>
                        <p>Precise calculations using validated models and historical datasets</p>
                    </article>
                    <article class="feature-card fade-in-element">
                        <div class="icon-wrap icon-wrap--feature" aria-hidden="true"><i data-feather="layers"></i></div>
                        <h3>Mitigation Planning</h3>
                        <p>Develop evidence-based strategies for erosion control and coastal protection</p>
                    </article>
                    <article class="feature-card fade-in-element">
                        <div class="icon-wrap icon-wrap--feature" aria-hidden="true"><i data-feather="trending-up"></i></div>
                        <h3>Predictive Analytics</h3>
                        <p>Forecast erosion patterns using climate models and machine learning</p>
                    </article>
                </div>
            </div>
        </section>

        {{-- How It Works --}}
        <section id="how-it-works" class="section how-it-works">
            <div class="container">
                <div class="section-header fade-in-element">
                    <h2>How It Works</h2>
                    <p>A structured process for reliable erosion assessment</p>
                </div>
                <div class="steps-grid">
                    <article class="step fade-in-element">
                        <span class="step-number" aria-hidden="true">1</span>
                        <h3>Data Collection</h3>
                        <p>Gather topographical data, soil samples, and environmental measurements from target coastal areas</p>
                    </article>
                    <article class="step fade-in-element">
                        <span class="step-number" aria-hidden="true">2</span>
                        <h3>Analysis</h3>
                        <p>Process data through validated algorithms to identify erosion patterns and risk factors</p>
                    </article>
                    <article class="step fade-in-element">
                        <span class="step-number" aria-hidden="true">3</span>
                        <h3>Assessment</h3>
                        <p>Generate comprehensive reports with erosion risk scores and soil loss projections</p>
                    </article>
                    <article class="step fade-in-element">
                        <span class="step-number" aria-hidden="true">4</span>
                        <h3>Planning</h3>
                        <p>Receive customized mitigation strategies and implementation recommendations</p>
                    </article>
                </div>
            </div>
        </section>
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
            <p class="footer-copyright">&copy; 2026 TOCSEA. All rights reserved.</p>
        </div>
    </footer>

    <div id="notification" class="notification" aria-live="polite" role="status" hidden></div>
</body>
</html>
