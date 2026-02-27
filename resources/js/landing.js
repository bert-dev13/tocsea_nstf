/**
 * TOCSEA Landing Page - Vanilla JavaScript
 * Handles smooth scroll, fade-in animations, mobile menu, and Feather icons
 */

import feather from 'feather-icons';

document.addEventListener('DOMContentLoaded', () => {
    initFeatherIcons();
    initHeroAnimation();
    initSmoothScroll();
    initScrollAnimations();
    initNotifications();
});

/**
 * Replace all [data-feather] placeholders with Feather icon SVGs.
 * Call after DOM ready and after any dynamic content (e.g. modal) is shown.
 */
function initFeatherIcons() {
    feather.replace({ 'aria-hidden': 'true' });
}

/**
 * When the page is restored from bfcache (back/forward cache), clear focus
 * from nav buttons so they don't retain a hover-like appearance on load.
 */
window.addEventListener('pageshow', (event) => {
    if (event.persisted) {
        const navBtns = document.querySelectorAll('.top-nav-btn');
        navBtns.forEach((btn) => {
            if (document.activeElement === btn) {
                btn.blur();
            }
        });
    }
});

/**
 * Hero section entrance animation on page load
 */
function initHeroAnimation() {
    const hero = document.getElementById('hero');
    if (!hero) return;

    requestAnimationFrame(() => {
        hero.classList.add('hero-visible');
    });
}

/**
 * Smooth scroll for anchor links and data-scroll-to buttons
 */
function initSmoothScroll() {
    document.querySelectorAll('a[href^="#"], [data-scroll-to]').forEach((el) => {
        el.addEventListener('click', (e) => {
            const id = el.getAttribute('href')?.slice(1) || el.getAttribute('data-scroll-to');
            if (!id || id === '') return;

            const target = document.getElementById(id);
            if (target) {
                e.preventDefault();
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });
}

/**
 * Intersection Observer for fade-in elements
 */
function initScrollAnimations() {
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -40px 0px',
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);

    document.querySelectorAll('.fade-in-element').forEach((el) => observer.observe(el));
}

/**
 * Demo request (no modal)
 */
function initNotifications() {
    const demoBtn = document.querySelector('[data-action="demo"]');
    if (demoBtn) {
        demoBtn.addEventListener('click', () => {
            showNotification('Demo request received. Our team will contact you within 24 hours.', 'info');
        });
    }
}

/**
 * Show toast notification
 */
function showNotification(message, type = 'info') {
    const container = document.getElementById('notification');
    if (!container) return;

    container.textContent = message;
    container.className = `notification notification-${type} is-visible`;
    container.removeAttribute('hidden');

    setTimeout(() => {
        container.classList.remove('is-visible');
        container.setAttribute('hidden', '');
    }, 5000);
}
