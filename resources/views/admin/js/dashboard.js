/**
 * TOCSEA Admin Dashboard - Sidebar, icons, animations
 */

import {
    createIcons,
    LayoutDashboard,
    Users,
    Layers,
    Settings,
    BarChart2,
    Shield,
    Calculator,
    Calendar,
    Box,
    UserPlus,
    Clock,
    Inbox,
    Eye,
    LogOut,
    User,
    ExternalLink,
    PanelLeft,
    Activity,
    TrendingUp,
    MapPin,
} from 'lucide';

const ADMIN_ICONS = {
    LayoutDashboard,
    Users,
    Layers,
    Settings,
    BarChart2,
    Shield,
    Calculator,
    Calendar,
    Box,
    UserPlus,
    Clock,
    Inbox,
    Eye,
    LogOut,
    User,
    ExternalLink,
    PanelLeft,
    Activity,
    TrendingUp,
    MapPin,
};

document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.querySelector('.admin-sidebar');
    const topbar = document.querySelector('.admin-topbar');
    const main = document.querySelector('.admin-main');
    if (sidebar) createIcons({ icons: ADMIN_ICONS, attrs: { 'stroke-width': 2 }, root: sidebar });
    if (topbar) createIcons({ icons: ADMIN_ICONS, attrs: { 'stroke-width': 2 }, root: topbar });
    if (main) createIcons({ icons: ADMIN_ICONS, attrs: { 'stroke-width': 2 }, root: main });
    initScrollAnimations();
    initSidebarToggle();
});

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

function initSidebarToggle() {
    const toggle = document.getElementById('adminSidebarToggle');
    const sidebar = document.getElementById('adminSidebar');
    if (!toggle || !sidebar) return;

    // On mobile, start with sidebar collapsed
    if (window.innerWidth <= 768) {
        sidebar.setAttribute('aria-hidden', 'true');
        toggle.setAttribute('aria-expanded', 'false');
    }

    toggle.addEventListener('click', () => {
        const isHidden = sidebar.getAttribute('aria-hidden') === 'true';
        sidebar.setAttribute('aria-hidden', !isHidden);
        toggle.setAttribute('aria-expanded', isHidden);
        if (sidebar) createIcons({ icons: ADMIN_ICONS, attrs: { 'stroke-width': 2 }, root: sidebar });
        if (topbar) createIcons({ icons: ADMIN_ICONS, attrs: { 'stroke-width': 2 }, root: topbar });
    });
}
