@extends('layouts.admin')

@section('title', 'Settings')
@section('page-title', 'Settings')

@section('content')
<div class="admin-hub admin-settings-page" id="adminSettingsPage"
    data-profile-url="{{ route('admin.settings.profile.update') }}"
    data-password-url="{{ route('admin.settings.password.update') }}"
    data-csrf="{{ csrf_token() }}">
    <header class="admin-header fade-in-element">
        <div class="admin-header-card">
            <div class="admin-header-main">
                <div>
                    <h2 class="admin-header-title">
                        <i data-lucide="settings" class="lucide-icon lucide-icon-md" aria-hidden="true"></i>
                        Account Settings
                    </h2>
                    <p class="admin-header-meta">
                        Manage your personal information and password
                    </p>
                </div>
            </div>
        </div>
    </header>

    <div class="admin-settings-toast" id="adminSettingsToast" role="status" aria-live="polite" hidden>
        <i data-lucide="check-circle" class="lucide-icon lucide-icon-sm" aria-hidden="true"></i>
        <span id="adminSettingsToastMessage"></span>
    </div>

    <section class="admin-section admin-settings-section fade-in-element">
        <h3 class="admin-section-title">
            <i data-lucide="user" class="lucide-icon lucide-icon-md" aria-hidden="true"></i>
            Personal Information
        </h3>
        <div class="admin-settings-card">
            <form id="adminProfileForm" class="admin-settings-form" novalidate>
                @csrf
                <div class="admin-settings-form-row">
                    <label for="adminSettingsName" class="admin-settings-label">Full Name <span class="admin-settings-required">*</span></label>
                    <input type="text" id="adminSettingsName" name="name" class="admin-settings-input" value="{{ old('name', $user->name) }}" required maxlength="255" autocomplete="name">
                    <span class="admin-settings-error" id="adminSettingsNameError" role="alert" hidden></span>
                </div>
                <div class="admin-settings-form-row">
                    <label for="adminSettingsEmail" class="admin-settings-label">Email Address <span class="admin-settings-required">*</span></label>
                    <input type="email" id="adminSettingsEmail" name="email" class="admin-settings-input" value="{{ old('email', $user->email) }}" required maxlength="255" autocomplete="email">
                    <span class="admin-settings-error" id="adminSettingsEmailError" role="alert" hidden></span>
                </div>
                <div class="admin-settings-form-row">
                    <label class="admin-settings-label">Account Created</label>
                    <div class="admin-settings-readonly">{{ $user->created_at->format('F j, Y') }}</div>
                </div>
                <div class="admin-settings-form-actions">
                    <button type="submit" class="admin-settings-btn admin-settings-btn-primary" id="adminProfileSubmitBtn">Save Changes</button>
                </div>
            </form>
        </div>
    </section>

    <section class="admin-section admin-settings-section fade-in-element">
        <h3 class="admin-section-title">
            <i data-lucide="lock" class="lucide-icon lucide-icon-md" aria-hidden="true"></i>
            Change Password
        </h3>
        <div class="admin-settings-card">
            <form id="adminPasswordForm" class="admin-settings-form" novalidate>
                @csrf
                <div class="admin-settings-form-row">
                    <label for="adminSettingsCurrentPassword" class="admin-settings-label">Current Password <span class="admin-settings-required">*</span></label>
                    <input type="password" id="adminSettingsCurrentPassword" name="current_password" class="admin-settings-input" autocomplete="current-password" required>
                    <span class="admin-settings-error" id="adminSettingsCurrentPasswordError" role="alert" hidden></span>
                </div>
                <div class="admin-settings-form-row">
                    <label for="adminSettingsNewPassword" class="admin-settings-label">New Password <span class="admin-settings-required">*</span></label>
                    <input type="password" id="adminSettingsNewPassword" name="new_password" class="admin-settings-input" autocomplete="new-password" required minlength="8">
                    <span class="admin-settings-hint">Minimum 8 characters.</span>
                    <span class="admin-settings-error" id="adminSettingsNewPasswordError" role="alert" hidden></span>
                </div>
                <div class="admin-settings-form-row">
                    <label for="adminSettingsNewPasswordConfirmation" class="admin-settings-label">Confirm New Password <span class="admin-settings-required">*</span></label>
                    <input type="password" id="adminSettingsNewPasswordConfirmation" name="new_password_confirmation" class="admin-settings-input" autocomplete="new-password" required minlength="8">
                    <span class="admin-settings-error" id="adminSettingsNewPasswordConfirmationError" role="alert" hidden></span>
                </div>
                <div class="admin-settings-form-actions">
                    <button type="submit" class="admin-settings-btn admin-settings-btn-primary" id="adminPasswordSubmitBtn">Change Password</button>
                </div>
            </form>
        </div>
    </section>
</div>
@endsection
