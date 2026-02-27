/**
 * TOCSEA Login Page - Feather icons and client-side validation
 * PSGC cascading location dropdowns for Philippines registration
 */

import feather from 'feather-icons';

const PSGC_BASE = 'https://psgc.gitlab.io/api';

document.addEventListener('DOMContentLoaded', () => {
    initFeatherIcons();
    initAuthForms();
    initPsgcSelects();
    initPasswordFeedback();
    initPasswordToggles();
});

function initFeatherIcons() {
    feather.replace({ 'aria-hidden': 'true' });
}

function initAuthForms() {
    const loginForm = document.getElementById('loginForm');
    const registerForm = document.getElementById('registerForm');
    const forgotForm = document.getElementById('forgotPasswordForm');

    if (loginForm) setupFormValidation(loginForm, {
        email: { required: true, email: true },
        password: { required: true }
    });

    if (registerForm) setupFormValidation(registerForm, {
        name: { required: true, minLength: 2 },
        email: { required: true, email: true },
        province: { required: true },
        municipality: { required: true },
        barangay: { required: true },
        password: { required: true, minLength: 8 },
        password_confirmation: { required: true, match: 'password' }
    });

    if (forgotForm) setupFormValidation(forgotForm, {
        email: { required: true, email: true }
    });
}

function setupFormValidation(form, rules) {
    const inputs = form.querySelectorAll('input[required], input[name], select[required]');
    inputs.forEach(input => {
        input.addEventListener('blur', () => validateField(input, rules[input.name]));
        input.addEventListener('input', () => clearFieldError(input));
    });

    form.addEventListener('submit', (e) => {
        let valid = true;
        inputs.forEach(input => {
            const rule = rules[input.name];
            if (rule && !validateField(input, rule)) valid = false;
        });
        if (!valid) e.preventDefault();
    });
}

function validateField(input, rules) {
    if (!rules) return true;
    const value = input.value.trim();
    let error = '';

    if (rules.required && !value) {
        error = 'This field is required.';
    } else if (rules.email && value && !isValidEmail(value)) {
        error = 'Please enter a valid email address.';
    } else if (rules.minLength && value.length < rules.minLength) {
        error = `Minimum ${rules.minLength} characters required.`;
    } else if (rules.match) {
        const matchInput = input.closest('form').querySelector(`[name="${rules.match}"]`);
        if (matchInput && value !== matchInput.value) {
            error = 'Passwords do not match.';
        }
    }

    showFieldError(input, error);
    return !error;
}

function isValidEmail(str) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(str);
}

function showFieldError(input, message) {
    clearFieldError(input);
    input.classList.toggle('is-invalid', !!message);
    input.setAttribute('aria-invalid', !!message);

    if (message) {
        const errorEl = document.createElement('span');
        errorEl.className = 'form-error';
        errorEl.setAttribute('role', 'alert');
        errorEl.textContent = message;
        errorEl.id = `${input.name}-client-error`;
        input.closest('.form-group').appendChild(errorEl);
    }
}

function clearFieldError(input) {
    input.classList.remove('is-invalid');
    input.setAttribute('aria-invalid', 'false');
    const clientError = input.closest('.form-group')?.querySelector(`#${input.name}-client-error`);
    if (clientError) clientError.remove();
}

/**
 * PSGC cascading dropdowns: Province → Municipality → Barangay
 */
function initPsgcSelects() {
    const provinceSelect = document.getElementById('province');
    const municipalitySelect = document.getElementById('municipality');
    const barangaySelect = document.getElementById('barangay');

    if (!provinceSelect || !municipalitySelect || !barangaySelect) return;

    const setLoading = (el, loading) => {
        const wrap = el.closest('.input-wrap-select');
        if (wrap) wrap.classList.toggle('is-loading', loading);
    };

    const resetSelect = (select, placeholder) => {
        select.innerHTML = `<option value="">${placeholder}</option>`;
        select.disabled = true;
        select.value = '';
    };

    const populateOptions = (select, items, placeholder) => {
        select.innerHTML = `<option value="">${placeholder}</option>`;
        items.forEach(({ code, name }) => {
            const opt = document.createElement('option');
            opt.value = name;
            opt.textContent = name;
            opt.dataset.code = code;
            select.appendChild(opt);
        });
        select.disabled = false;
    };

    const loadProvinces = async () => {
        setLoading(provinceSelect, true);
        try {
            const res = await fetch(`${PSGC_BASE}/provinces/`);
            if (!res.ok) throw new Error('Failed to load provinces');
            const data = await res.json();
            populateOptions(provinceSelect, data, 'Select Province');
        } catch (err) {
            provinceSelect.innerHTML = `<option value="">Error loading provinces</option>`;
        } finally {
            setLoading(provinceSelect, false);
            feather.replace({ 'aria-hidden': 'true' });
        }
    };

    const loadMunicipalities = async (provinceCode) => {
        resetSelect(municipalitySelect, 'Select Municipality');
        resetSelect(barangaySelect, 'Select Barangay');
        setLoading(municipalitySelect, true);
        try {
            const res = await fetch(`${PSGC_BASE}/provinces/${provinceCode}/municipalities/`);
            if (!res.ok) throw new Error('Failed to load municipalities');
            const data = await res.json();
            populateOptions(municipalitySelect, data, 'Select Municipality');
        } catch (err) {
            municipalitySelect.innerHTML = `<option value="">Error loading municipalities</option>`;
        } finally {
            setLoading(municipalitySelect, false);
            feather.replace({ 'aria-hidden': 'true' });
        }
    };

    const loadBarangays = async (municipalityCode) => {
        resetSelect(barangaySelect, 'Select Barangay');
        setLoading(barangaySelect, true);
        try {
            const res = await fetch(`${PSGC_BASE}/municipalities/${municipalityCode}/barangays/`);
            if (!res.ok) throw new Error('Failed to load barangays');
            const data = await res.json();
            populateOptions(barangaySelect, data, 'Select Barangay');
        } catch (err) {
            barangaySelect.innerHTML = `<option value="">Error loading barangays</option>`;
        } finally {
            setLoading(barangaySelect, false);
            feather.replace({ 'aria-hidden': 'true' });
        }
    };

    provinceSelect.addEventListener('change', () => {
        const opt = provinceSelect.selectedOptions[0];
        const code = opt?.dataset?.code;
        if (code) loadMunicipalities(code);
        else {
            resetSelect(municipalitySelect, 'Select Municipality');
            resetSelect(barangaySelect, 'Select Barangay');
        }
    });

    municipalitySelect.addEventListener('change', () => {
        const opt = municipalitySelect.selectedOptions[0];
        const code = opt?.dataset?.code;
        if (code) loadBarangays(code);
        else resetSelect(barangaySelect, 'Select Barangay');
    });

    loadProvinces();
}

/**
 * Password strength indicator and confirmation match feedback
 */
function initPasswordFeedback() {
    const passwordInput = document.getElementById('password');
    const confirmInput = document.getElementById('password_confirmation');
    const strengthEl = document.querySelector('.password-strength');
    const strengthBar = document.querySelector('.password-strength-bar');
    const strengthFill = document.querySelector('.password-strength-fill');
    const strengthText = document.querySelector('.password-strength-text');
    const confirmWrap = document.querySelector('.input-wrap-confirm');
    const confirmStatus = document.getElementById('password_confirmation-status');

    if (!passwordInput || !strengthEl || !strengthBar || !strengthFill || !strengthText) return;

    function getPasswordStrength(pwd) {
        if (!pwd || pwd.length === 0) return { level: 'empty', percent: 0, label: '' };
        let score = 0;
        if (pwd.length >= 8) score += 25;
        if (pwd.length >= 12) score += 15;
        if (/[a-z]/.test(pwd)) score += 20;
        if (/[A-Z]/.test(pwd)) score += 20;
        if (/[0-9]/.test(pwd)) score += 20;
        if (/[^a-zA-Z0-9]/.test(pwd)) score += 20;
        const percent = Math.min(score, 100);
        let level = 'weak', label = 'Weak';
        if (percent >= 67) { level = 'strong'; label = 'Strong'; }
        else if (percent >= 34) { level = 'medium'; label = 'Medium'; }
        else if (percent > 0) { level = 'weak'; label = 'Weak'; }
        return { level, percent, label };
    }

    function updateStrength() {
        const pwd = passwordInput.value;
        const { level, percent, label } = getPasswordStrength(pwd);
        strengthEl.classList.remove('weak', 'medium', 'strong');
        if (level === 'empty') {
            strengthEl.setAttribute('hidden', '');
        } else {
            strengthEl.removeAttribute('hidden');
            strengthEl.classList.add(level);
            strengthFill.style.setProperty('--strength-width', `${percent}%`);
            strengthBar.setAttribute('aria-valuenow', percent);
            strengthBar.setAttribute('aria-label', `Password strength: ${label}`);
            strengthText.textContent = `Password strength: ${label}`;
        }
    }

    function updateConfirmFeedback() {
        if (!confirmWrap || !confirmStatus) return;
        const pwd = passwordInput.value;
        const conf = confirmInput.value;
        confirmWrap.classList.remove('is-match', 'is-mismatch');
        confirmStatus.classList.remove('match', 'mismatch');
        confirmStatus.setAttribute('aria-hidden', 'true');
        confirmStatus.innerHTML = '';

        if (conf.length === 0) return;

        if (pwd === conf) {
            confirmWrap.classList.add('is-match');
            confirmStatus.classList.add('match');
            confirmStatus.setAttribute('aria-hidden', 'false');
            confirmStatus.innerHTML = '<i data-feather="check" aria-hidden="true"></i>';
            confirmStatus.setAttribute('aria-label', 'Passwords match');
            feather.replace({ 'aria-hidden': 'true' });
        } else {
            confirmWrap.classList.add('is-mismatch');
            confirmStatus.classList.add('mismatch');
            confirmStatus.setAttribute('aria-hidden', 'false');
            confirmStatus.innerHTML = '<i data-feather="x" aria-hidden="true"></i>';
            confirmStatus.setAttribute('aria-label', 'Passwords do not match');
            feather.replace({ 'aria-hidden': 'true' });
        }
    }

    passwordInput.addEventListener('input', () => {
        updateStrength();
        if (confirmInput?.value) updateConfirmFeedback();
    });
    passwordInput.addEventListener('blur', updateStrength);
    if (confirmInput) {
        confirmInput.addEventListener('input', updateConfirmFeedback);
        confirmInput.addEventListener('blur', updateConfirmFeedback);
    }

    updateStrength();
}

/**
 * Password show/hide toggle (eye icon)
 * Note: initFeatherIcons runs first and replaces [data-feather] with SVG,
 * so we must not rely on finding [data-feather] - we replace the icon via innerHTML.
 */
function initPasswordToggles() {
    document.querySelectorAll('[data-password-toggle]').forEach(btn => {
        const input = btn.closest('.input-wrap-password')?.querySelector('input');
        if (!input) return;

        btn.addEventListener('click', () => {
            const isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';
            btn.setAttribute('aria-pressed', isPassword ? 'true' : 'false');
            btn.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
            btn.setAttribute('title', isPassword ? 'Hide password' : 'Show password');
            btn.innerHTML = feather.icons[isPassword ? 'eye-off' : 'eye'].toSvg({ 'aria-hidden': 'true' });
        });
    });
}
