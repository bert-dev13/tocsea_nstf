<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TOCSEA - Topographical Changes from Coastal Soil Erosion</title>
    <meta name="description" content="TOCSEA supports coastal soil loss monitoring, risk assessment, and environmental decision support through an integrated workflow: monitor, predict, customize models, analyze, review history, and get guidance.">
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
                        <p class="hero-subtitle">Coastal Soil Loss Monitoring and Environmental Decision Support</p>
                        <p class="hero-description">
                            TOCSEA is designed for coastal soil loss monitoring, risk assessment, and environmental decision support. It integrates monitoring, prediction, custom modeling, and guidance in one platform to support researchers and coastal managers.
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
                    <h2>One Integrated System</h2>
                    <p>TOCSEA’s modules work together: the Dashboard gives you an environmental overview; the Soil Calculator predicts soil loss; the Model Builder lets you create and use custom regression equations; Calculation History stores and tracks results; Ask TOCSEA provides guidance and interpretation. These components form a single, connected workflow.</p>
                </div>
                <div class="about-tocsea-cards">
                    <article class="about-card fade-in-element">
                        <div class="icon-wrap" aria-hidden="true"><i data-feather="layout"></i></div>
                        <h3>Dashboard</h3>
                        <p>Provides the environmental overview and entry point to monitoring and system tools.</p>
                    </article>
                    <article class="about-card fade-in-element">
                        <div class="icon-wrap" aria-hidden="true"><i data-feather="bar-chart-2"></i></div>
                        <h3>Soil Calculator</h3>
                        <p>Predicts soil loss using validated models and site-specific parameters, feeding into risk assessment.</p>
                    </article>
                    <article class="about-card fade-in-element">
                        <div class="icon-wrap" aria-hidden="true"><i data-feather="layers"></i></div>
                        <h3>Model Builder</h3>
                        <p>Enables custom regression equations for your coastal study areas and supports tailored analysis.</p>
                    </article>
                    <article class="about-card fade-in-element">
                        <div class="icon-wrap" aria-hidden="true"><i data-feather="database"></i></div>
                        <h3>Calculation History & Ask TOCSEA</h3>
                        <p>History stores and tracks all results; Ask TOCSEA assists with guidance and interpretation of outputs.</p>
                    </article>
                </div>
            </div>
        </section>

        {{-- Core Features --}}
        <section id="features" class="section features">
            <div class="container">
                <div class="section-header fade-in-element">
                    <h2>How the Modules Connect</h2>
                    <p>Dashboard, Soil Calculator, Model Builder, Calculation History, and Ask TOCSEA are interconnected: overview and monitoring lead to prediction and custom models, results are stored and analyzed, and guidance is available throughout.</p>
                </div>
                <div class="features-grid">
                    <article class="feature-card fade-in-element">
                        <div class="icon-wrap icon-wrap--feature" aria-hidden="true"><i data-feather="eye"></i></div>
                        <h3>Monitor</h3>
                        <p>Use the Dashboard to view environmental conditions and access system tools.</p>
                    </article>
                    <article class="feature-card fade-in-element">
                        <div class="icon-wrap icon-wrap--feature" aria-hidden="true"><i data-feather="bar-chart-2"></i></div>
                        <h3>Predict</h3>
                        <p>Run the Soil Calculator to estimate soil loss and support risk assessment.</p>
                    </article>
                    <article class="feature-card fade-in-element">
                        <div class="icon-wrap icon-wrap--feature" aria-hidden="true"><i data-feather="layers"></i></div>
                        <h3>Customize & Analyze</h3>
                        <p>Build custom equations in Model Builder and analyze results across scenarios.</p>
                    </article>
                    <article class="feature-card fade-in-element">
                        <div class="icon-wrap icon-wrap--feature" aria-hidden="true"><i data-feather="book-open"></i></div>
                        <h3>Review & Get Guidance</h3>
                        <p>Review past runs in Calculation History and use Ask TOCSEA for interpretation and support.</p>
                    </article>
                </div>
            </div>
        </section>

        {{-- How It Works --}}
        <section id="how-it-works" class="section how-it-works">
            <div class="container">
                <div class="section-header fade-in-element">
                    <h2>Platform Workflow</h2>
                    <p>Monitor → Predict → Customize Model → Analyze Results → Review History → Get Guidance</p>
                </div>
                <div class="steps-grid">
                    <article class="step fade-in-element">
                        <span class="step-number" aria-hidden="true">1</span>
                        <h3>Monitor</h3>
                        <p>Start from the Dashboard for an environmental overview and access to all modules.</p>
                    </article>
                    <article class="step fade-in-element">
                        <span class="step-number" aria-hidden="true">2</span>
                        <h3>Predict</h3>
                        <p>Use the Soil Calculator to predict soil loss and support risk assessment.</p>
                    </article>
                    <article class="step fade-in-element">
                        <span class="step-number" aria-hidden="true">3</span>
                        <h3>Customize Model</h3>
                        <p>Build or adjust regression equations in Model Builder for your study area.</p>
                    </article>
                    <article class="step fade-in-element">
                        <span class="step-number" aria-hidden="true">4</span>
                        <h3>Analyze Results</h3>
                        <p>Interpret outputs from calculations and models to inform decisions.</p>
                    </article>
                    <article class="step fade-in-element">
                        <span class="step-number" aria-hidden="true">5</span>
                        <h3>Review History</h3>
                        <p>Store and track runs in Calculation History for comparison and audit.</p>
                    </article>
                    <article class="step fade-in-element">
                        <span class="step-number" aria-hidden="true">6</span>
                        <h3>Get Guidance</h3>
                        <p>Use Ask TOCSEA for clarification, interpretation, and support.</p>
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
                <p class="footer-tagline">Coastal Soil Loss Monitoring and Environmental Decision Support</p>
                <a href="mailto:info@tocsea.com" class="footer-email">info@tocsea.com</a>
            </div>
            <div class="footer-divider" aria-hidden="true"></div>
            <p class="footer-copyright">&copy; 2026 TOCSEA. All rights reserved.</p>
        </div>
    </footer>

    <div id="notification" class="notification" aria-live="polite" role="status" hidden></div>
</body>
</html>
