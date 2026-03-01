/**
 * TOCSEA Admin Settings - Profile update & Change password (same as user side)
 */

import { createIcons, User, Mail, Lock, Settings, CheckCircle } from 'lucide';

const ICONS = { User, Mail, Lock, Settings, CheckCircle };

function initAdminSettings() {
    const page = document.getElementById('adminSettingsPage');
    if (!page) return;

    createIcons({ icons: ICONS, attrs: { 'stroke-width': 2 } });

    const profileUrl = page.dataset.profileUrl;
    const passwordUrl = page.dataset.passwordUrl;
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || page.dataset.csrf;

    const toast = document.getElementById('adminSettingsToast');
    const toastMessage = document.getElementById('adminSettingsToastMessage');

    function showToast(message) {
        if (!toast || !toastMessage) return;
        toastMessage.textContent = message;
        toast.hidden = false;
        toast.classList.add('is-visible');
        setTimeout(() => {
            toast.classList.remove('is-visible');
            setTimeout(() => { toast.hidden = true; }, 300);
        }, 3000);
    }

    function clearProfileErrors() {
        ['adminSettingsName', 'adminSettingsEmail'].forEach((id) => {
            const el = document.getElementById(id);
            const err = document.getElementById(id + 'Error');
            if (el) el.classList.remove('is-error');
            if (err) { err.hidden = true; err.textContent = ''; }
        });
    }

    function showProfileErrors(errors) {
        clearProfileErrors();
        const map = { name: 'adminSettingsName', email: 'adminSettingsEmail' };
        Object.keys(errors || {}).forEach((key) => {
            const fieldId = map[key] || key;
            const errEl = document.getElementById(fieldId + 'Error');
            const inputEl = document.getElementById(fieldId);
            const msg = Array.isArray(errors[key]) ? errors[key][0] : errors[key];
            if (errEl) { errEl.textContent = msg; errEl.hidden = false; }
            if (inputEl) inputEl.classList.add('is-error');
        });
    }

    function clearPasswordErrors() {
        ['adminSettingsCurrentPassword', 'adminSettingsNewPassword', 'adminSettingsNewPasswordConfirmation'].forEach((id) => {
            const err = document.getElementById(id + 'Error');
            const input = document.getElementById(id);
            if (err) { err.hidden = true; err.textContent = ''; }
            if (input) input.classList.remove('is-error');
        });
    }

    function showPasswordErrors(errors) {
        clearPasswordErrors();
        const map = {
            current_password: 'adminSettingsCurrentPassword',
            new_password: 'adminSettingsNewPassword',
            new_password_confirmation: 'adminSettingsNewPasswordConfirmation',
        };
        Object.keys(errors || {}).forEach((key) => {
            const fieldId = map[key];
            if (!fieldId) return;
            const errEl = document.getElementById(fieldId + 'Error');
            const inputEl = document.getElementById(fieldId);
            const msg = Array.isArray(errors[key]) ? errors[key][0] : errors[key];
            if (errEl) { errEl.textContent = msg; errEl.hidden = false; }
            if (inputEl) inputEl.classList.add('is-error');
        });
    }

    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    document.getElementById('adminProfileForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        clearProfileErrors();

        const nameInput = document.getElementById('adminSettingsName');
        const emailInput = document.getElementById('adminSettingsEmail');
        const name = nameInput?.value?.trim();
        const email = emailInput?.value?.trim();

        const errs = {};
        if (!name) errs.name = 'Full name is required.';
        if (!email) errs.email = 'Email address is required.';
        else if (!emailRegex.test(email)) errs.email = 'Please enter a valid email address.';

        if (Object.keys(errs).length) {
            showProfileErrors(errs);
            return;
        }

        const btn = document.getElementById('adminProfileSubmitBtn');
        if (btn) { btn.disabled = true; btn.textContent = 'Saving…'; }

        try {
            const res = await fetch(profileUrl, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({ name, email }),
            });

            const data = await res.json().catch(() => ({}));

            if (!res.ok) {
                showProfileErrors(data.errors || { name: data.message || 'Update failed.' });
                return;
            }

            showToast(data.message || 'Profile updated successfully.');
        } catch {
            showProfileErrors({ name: 'Unable to save. Please try again.' });
        } finally {
            if (btn) { btn.disabled = false; btn.textContent = 'Save Changes'; }
        }
    });

    document.getElementById('adminPasswordForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        clearPasswordErrors();

        const current = document.getElementById('adminSettingsCurrentPassword')?.value ?? '';
        const newPw = document.getElementById('adminSettingsNewPassword')?.value ?? '';
        const confirm = document.getElementById('adminSettingsNewPasswordConfirmation')?.value ?? '';

        const errs = {};
        if (!current) errs.current_password = 'Current password is required.';
        if (!newPw) errs.new_password = 'New password is required.';
        else if (newPw.length < 8) errs.new_password = 'New password must be at least 8 characters.';
        if (newPw !== confirm) errs.new_password_confirmation = 'New password and confirmation do not match.';

        if (Object.keys(errs).length) {
            showPasswordErrors(errs);
            return;
        }

        const btn = document.getElementById('adminPasswordSubmitBtn');
        if (btn) { btn.disabled = true; btn.textContent = 'Updating…'; }

        try {
            const res = await fetch(passwordUrl, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({
                    current_password: current,
                    new_password: newPw,
                    new_password_confirmation: confirm,
                }),
            });

            const data = await res.json().catch(() => ({}));

            if (!res.ok) {
                showPasswordErrors(data.errors || { current_password: data.message || 'Update failed.' });
                return;
            }

            showToast(data.message || 'Password changed successfully.');
            document.getElementById('adminSettingsCurrentPassword').value = '';
            document.getElementById('adminSettingsNewPassword').value = '';
            document.getElementById('adminSettingsNewPasswordConfirmation').value = '';
        } catch {
            showPasswordErrors({ current_password: 'Unable to update. Please try again.' });
        } finally {
            if (btn) { btn.disabled = false; btn.textContent = 'Change Password'; }
        }
    });
}

document.addEventListener('DOMContentLoaded', initAdminSettings);
