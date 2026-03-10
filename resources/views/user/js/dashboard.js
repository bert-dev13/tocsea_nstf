/**
 * TOCSEA Dashboard Hub - Weather, Soil, Maps
 * Weather: 2D emoji | Other: Lucide outline
 */

import {
    createIcons,
    Activity,
    History,
    LayoutDashboard,
    AlertTriangle,
    Lightbulb,
    RotateCcw,
    Info,
    BarChart2,
    Box,
    Calendar,
    Calculator,
    ChevronDown,
    Cloud,
    CloudLightning,
    CloudRain,
    CloudSnow,
    CloudSun,
    Droplets,
    Eye,
    Folder,
    Gauge,
    Inbox,
    Layers,
    Leaf,
    LogOut,
    Map,
    MapPin,
    MessageCircle,
    Clock,
    PlusCircle,
    Settings,
    Shield,
    ShieldAlert,
    SlidersHorizontal,
    Sun,
    TreePine,
    TrendingUp,
    Download,
    User,
    Wind,
    Zap,
    ArrowLeft,
    Bookmark,
    Play,
    Plus,
} from 'lucide';

const DASHBOARD_ICONS = {
    Activity,
    History,
    LayoutDashboard,
    AlertTriangle,
    Shield,
    Lightbulb,
    RotateCcw,
    Info,
    BarChart2,
    Box,
    Calendar,
    Clock,
    MessageCircle,
    Calculator,
    ChevronDown,
    Cloud,
    CloudLightning,
    CloudRain,
    CloudSnow,
    CloudSun,
    Droplets,
    Eye,
    Folder,
    Gauge,
    Inbox,
    Layers,
    Leaf,
    LogOut,
    Map,
    MapPin,
    PlusCircle,
    Settings,
    ShieldAlert,
    SlidersHorizontal,
    Sun,
    TreePine,
    TrendingUp,
    Download,
    User,
    Wind,
    Zap,
    ArrowLeft,
    Bookmark,
    Play,
    Plus,
};

/** Map OpenWeatherMap condition ID to Lucide icon name */
function getWeatherLucideIcon(condition) {
    if (!condition || condition.id == null) return 'sun';
    const id = condition.id;
    if (id >= 200 && id < 300) return 'cloud-lightning';
    if (id >= 300 && id < 400) return 'cloud-rain';
    if (id >= 500 && id < 600) return 'cloud-rain';
    if (id >= 600 && id < 700) return 'cloud-snow';
    if (id >= 700 && id < 800) return 'cloud';
    if (id === 800) return 'sun';
    if (id === 801 || id === 802) return 'cloud-sun';
    return 'cloud';
}

/** Whether to use cloud float animation */
function isCloudIcon(iconName) {
    return ['cloud', 'cloud-sun', 'cloud-rain', 'cloud-snow', 'cloud-lightning'].includes(iconName);
}

/** Whether to use sun rotation animation */
function isSunIcon(iconName) {
    return iconName === 'sun';
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/** Convert temp °C → °F */
function celsiusToFahrenheit(c) {
    return Math.round((c * 9) / 5 + 32);
}

/** Format time from Unix timestamp */
function formatTime(ts) {
    if (!ts) return '—';
    const d = new Date(ts * 1000);
    return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

/** Format date for display */
function formatTodayDate() {
    return new Date().toLocaleDateString([], {
        weekday: 'long',
        month: 'short',
        day: 'numeric',
    });
}

/** Generate smart weather insight text */
function getWeatherInsight(data) {
    if (!data) return '';
    const { current, forecast } = data;
    const humidity = current?.humidity ?? 0;
    const popToday = forecast?.[0]?.pop ?? 0;
    const condId = current?.condition?.id ?? 800;

    if (humidity >= 80) return 'High humidity today — stay hydrated.';
    if (humidity >= 65 && humidity < 80) return 'Moderate humidity — comfortable conditions.';
    if (condId === 800 && forecast?.[1]?.condition?.id === 800)
        return 'Clear skies expected tomorrow — good for outdoor activities.';
    if (condId >= 500 && condId < 600) return 'Rain expected — bring an umbrella.';
    if (popToday >= 60) return 'High chance of rain today — plan indoor activities.';
    if (condId >= 700 && condId < 800) return 'Hazy conditions — avoid prolonged outdoor exposure.';
    if (condId >= 200 && condId < 300) return 'Thunderstorms possible — stay indoors.';
    return 'Enjoy the day — conditions look favorable.';
}

let weatherUnit = 'c';
let weatherData = null;

document.addEventListener('DOMContentLoaded', () => {
    createIcons({ icons: DASHBOARD_ICONS, attrs: { 'stroke-width': 2 } });
    initScrollAnimations();
    initUserMenu();
    initMobileNav();
    loadWeather();
    initQuickActions();
});

function initUserMenu() {
    const trigger = document.querySelector('.top-nav-user-trigger');
    const dropdown = document.getElementById('top-nav-user-dropdown');
    if (!trigger || !dropdown) return;

    trigger.addEventListener('click', (e) => {
        e.stopPropagation();
        const isOpen = !dropdown.classList.contains('is-open');
        dropdown.classList.toggle('is-open', isOpen);
        dropdown.setAttribute('aria-hidden', !isOpen);
        trigger.setAttribute('aria-expanded', isOpen);
        if (isOpen) createIcons({ icons: DASHBOARD_ICONS, nameAttr: 'data-lucide' });
    });

    document.addEventListener('click', () => {
        dropdown.classList.remove('is-open');
        dropdown.setAttribute('aria-hidden', 'true');
        trigger.setAttribute('aria-expanded', 'false');
    });

    dropdown.addEventListener('click', (e) => {
        e.stopPropagation();
        const settingsLink = e.target.closest('[data-action="settings"]');
        if (settingsLink) {
            e.preventDefault();
            dropdown.classList.remove('is-open');
            dropdown.setAttribute('aria-hidden', 'true');
            trigger.setAttribute('aria-expanded', 'false');
            if (settingsLink.href && settingsLink.getAttribute('href') !== '#') {
                window.location.href = settingsLink.href;
            }
        }
    });

    trigger.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            trigger.click();
        }
        if (e.key === 'Escape') {
            dropdown.classList.remove('is-open');
            dropdown.setAttribute('aria-hidden', 'true');
            trigger.setAttribute('aria-expanded', 'false');
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && dropdown.classList.contains('is-open')) {
            dropdown.classList.remove('is-open');
            dropdown.setAttribute('aria-hidden', 'true');
            trigger.setAttribute('aria-expanded', 'false');
        }
    });
}

function initMobileNav() {
    const toggle = document.querySelector('.top-nav-menu-toggle');
    const mobileMenu = document.getElementById('top-nav-mobile-menu');
    if (!toggle || !mobileMenu) return;

    const closeMenu = () => {
        mobileMenu.classList.remove('is-open');
        mobileMenu.setAttribute('aria-hidden', 'true');
        toggle.setAttribute('aria-expanded', 'false');
        document.body.classList.remove('top-nav-mobile-open');
    };

    const openMenu = () => {
        mobileMenu.classList.add('is-open');
        mobileMenu.setAttribute('aria-hidden', 'false');
        toggle.setAttribute('aria-expanded', 'true');
        document.body.classList.add('top-nav-mobile-open');
    };

    toggle.addEventListener('click', (e) => {
        e.stopPropagation();
        const isOpen = mobileMenu.classList.contains('is-open');
        if (isOpen) {
            closeMenu();
        } else {
            openMenu();
        }
    });

    document.addEventListener('click', (e) => {
        if (!mobileMenu.classList.contains('is-open')) return;
        const target = e.target;
        if (target.closest('.top-nav-mobile-menu') || target.closest('.top-nav-menu-toggle')) return;
        closeMenu();
    });

    window.addEventListener('resize', () => {
        if (window.innerWidth >= 768 && mobileMenu.classList.contains('is-open')) {
            closeMenu();
        }
    });

    mobileMenu.querySelectorAll('a.top-nav-mobile-link').forEach((link) => {
        link.addEventListener('click', () => {
            closeMenu();
        });
    });

    const mobileLogoutBtn = mobileMenu.querySelector('[data-mobile-logout="true"]');
    if (mobileLogoutBtn) {
        mobileLogoutBtn.addEventListener('click', (e) => {
            e.preventDefault();
            closeMenu();
            const logoutForm = document.getElementById('logout-form');
            if (logoutForm) {
                logoutForm.submit();
            }
        });
    }
}

function initScrollAnimations() {
    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                }
            });
        },
        { threshold: 0.05, rootMargin: '0px 0px -20px 0px' }
    );
    document.querySelectorAll('.fade-in-element').forEach((el) => observer.observe(el));
}

function renderWeatherToDOM(data) {
    if (!data) return;
    weatherData = data;
    const today = data.current;
    const forecast = data.forecast || [];
    const todayDate = formatTodayDate();
    const isToday = (d) => {
        const y = new Date().toISOString().slice(0, 10);
        return d.date === y;
    };

    const displayTemp = (c) =>
        weatherUnit === 'f' ? celsiusToFahrenheit(c) : Math.round(parseFloat(c));
    const unitSuffix = weatherUnit === 'f' ? '°F' : '°C';

    // Today
    const locEl = document.getElementById('weatherLocation');
    const dateEl = document.getElementById('weatherDate');
    const tempEl = document.getElementById('weatherTempToday');
    const unitEl = document.getElementById('weatherTempUnit');
    const condEl = document.getElementById('weatherConditionToday');
    const feelsEl = document.getElementById('weatherFeelsLike');
    const humEl = document.getElementById('weatherHumidity');
    const windEl = document.getElementById('weatherWind');
    const pressureEl = document.getElementById('weatherPressure');
    const sunEl = document.getElementById('weatherSunTimes');
    const insightEl = document.getElementById('weatherInsight');
    const lastEl = document.getElementById('weatherLastUpdated');
    const iconWrap = document.getElementById('weatherIconWrap');
    const iconEl = document.getElementById('weatherIconToday');

    if (locEl) locEl.textContent = data.location?.display || '—';
    if (dateEl) dateEl.textContent = todayDate;
    if (tempEl) tempEl.textContent = displayTemp(today?.temp);
    if (unitEl) unitEl.textContent = unitSuffix;
    if (condEl) condEl.textContent = today?.condition?.description || '—';
    if (feelsEl) feelsEl.textContent = `${displayTemp(today?.feels_like)}${unitSuffix}`;
    if (humEl) humEl.textContent = today?.humidity != null ? `${today.humidity}%` : '—';
    if (windEl) windEl.textContent = today?.wind_speed != null ? `${today.wind_speed} km/h` : '—';
    if (pressureEl) pressureEl.textContent = today?.pressure != null ? `${today.pressure} hPa` : '—';
    if (sunEl)
        sunEl.textContent =
            today?.sunrise != null && today?.sunset != null
                ? `${formatTime(today.sunrise)} / ${formatTime(today.sunset)}`
                : '—';
    if (insightEl) {
        insightEl.textContent = getWeatherInsight(data);
        insightEl.hidden = !insightEl.textContent;
    }
    if (lastEl && data.updated_at) {
        const d = new Date(data.updated_at);
        lastEl.textContent = `Last updated ${d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}`;
    }

    const iconName = getWeatherLucideIcon(today?.condition);
    if (iconEl) {
        iconEl.setAttribute('data-lucide', iconName);
        createIcons({ icons: DASHBOARD_ICONS, nameAttr: 'data-lucide' });
    }
    if (iconWrap) {
        iconWrap.classList.remove('animate-sun', 'animate-cloud');
        if (isSunIcon(iconName)) iconWrap.classList.add('animate-sun');
        else if (isCloudIcon(iconName)) iconWrap.classList.add('animate-cloud');
    }

    // Forecast - row layout: Day | Icon | High | Low | Rain
    const forecastDaysEl = document.getElementById('forecastDays');
    if (forecastDaysEl && Array.isArray(forecast)) {
        forecastDaysEl.innerHTML = forecast
            .map((day) => {
                const icon = getWeatherLucideIcon(day.condition);
                const todayClass = isToday(day) ? ' forecast-day-today' : '';
                const iconAnimClass = isSunIcon(icon) ? ' forecast-icon-sun' : isCloudIcon(icon) ? ' forecast-icon-cloud' : '';
                const maxT = displayTemp(day.temp_max);
                const minT = displayTemp(day.temp_min);
                const pop = day.pop != null ? Math.round(day.pop) : null;
                return `
                <div class="forecast-row${todayClass}" data-date="${escapeHtml(day.date)}">
                    <span class="forecast-row-day">${escapeHtml(day.day_name)}</span>
                    <div class="forecast-row-icon${iconAnimClass}"><i data-lucide="${icon}" class="lucide-icon" aria-hidden="true"></i></div>
                    <span class="forecast-row-high">${maxT}°</span>
                    <span class="forecast-row-low">${minT}°</span>
                    <span class="forecast-row-rain">${pop != null ? `<i data-lucide="cloud-rain" class="lucide-icon lucide-icon-sm" aria-hidden="true"></i> ${pop}% rain` : '—'}</span>
                </div>`;
            })
            .join('');
        createIcons({ icons: DASHBOARD_ICONS, nameAttr: 'data-lucide' });
    }
}

function initTempToggle() {
    document.querySelectorAll('.weather-unit-btn').forEach((btn) => {
        btn.addEventListener('click', () => {
            const unit = btn.dataset.unit;
            weatherUnit = unit;
            document.querySelectorAll('.weather-unit-btn').forEach((b) => {
                b.classList.toggle('is-active', b.dataset.unit === unit);
                b.setAttribute('aria-pressed', b.dataset.unit === unit ? 'true' : 'false');
            });
            if (weatherData) renderWeatherToDOM(weatherData);
        });
    });
}

async function loadWeather() {
    const todayCard = document.getElementById('weatherTodayCard');
    const todayLoading = document.getElementById('weatherTodayLoading');
    const todayContent = document.getElementById('weatherTodayContent');
    const todayError = document.getElementById('weatherTodayError');
    const forecastLoading = document.getElementById('weatherForecastLoading');
    const forecastContent = document.getElementById('weatherForecastContent');
    const forecastError = document.getElementById('weatherForecastError');

    const showTodaySuccess = () => {
        todayLoading?.setAttribute('hidden', '');
        todayError?.setAttribute('hidden', '');
        todayContent?.removeAttribute('hidden');
        todayCard?.classList.add('weather-card-loaded');
    };
    const showTodayError = (msg) => {
        todayLoading?.setAttribute('hidden', '');
        todayContent?.setAttribute('hidden', '');
        todayError?.removeAttribute('hidden');
        const t = document.getElementById('weatherErrorText');
        if (t) t.textContent = msg || 'Unable to load weather';
    };
    const showForecastSuccess = () => {
        forecastLoading?.setAttribute('hidden', '');
        forecastError?.setAttribute('hidden', '');
        forecastContent?.removeAttribute('hidden');
    };
    const showForecastError = () => {
        forecastLoading?.setAttribute('hidden', '');
        forecastContent?.setAttribute('hidden', '');
        forecastError?.removeAttribute('hidden');
    };

    try {
        todayContent?.classList.add('weather-refreshing');
        const res = await fetch('/api/weather', {
            method: 'GET',
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        const data = await res.json();
        if (!res.ok) throw new Error(data.error || 'Failed to load weather');

        showTodaySuccess();
        showForecastSuccess();
        renderWeatherToDOM(data);
        initTempToggle();
        todayContent?.classList.remove('weather-refreshing');
    } catch (err) {
        showTodayError(err.message);
        showForecastError();
        todayContent?.classList.remove('weather-refreshing');
    }
}

function initQuickActions() {
    document.querySelectorAll('.quick-action-btn, .action-card, .top-nav-tool').forEach((el) => {
        el.addEventListener('click', (e) => {
            const href = el.getAttribute('href');
            const isHashLink = href && href.startsWith('#');
            if (isHashLink) {
                e.preventDefault();
            }
            const action = el.dataset?.action || el.getAttribute('data-action');
            if (action && isHashLink) {
                console.info('Quick action:', action);
            }
        });
    });
}
