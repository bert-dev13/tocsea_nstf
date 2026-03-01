@extends('layouts.dashboard')

@section('title', 'Settings')

@section('content')
<div class="dashboard-hub settings-page" id="settingsPage"
    data-profile-url="{{ route('settings.profile.update') }}"
    data-password-url="{{ route('settings.password.update') }}"
    data-csrf="{{ csrf_token() }}">
    <header class="dashboard-header fade-in-element">
        <div class="header-card">
            <div class="header-main">
                <div>
                    <h1 class="header-title">
                        <i data-lucide="settings" class="lucide-icon lucide-icon-md" aria-hidden="true"></i>
                        Account Settings
                    </h1>
                    <p class="header-location" style="color: rgba(255,255,255,0.9); font-weight: 400;">
                        Manage your personal information and password
                    </p>
                </div>
            </div>
        </div>
    </header>

    {{-- Toast for success messages --}}
    <div class="settings-toast" id="settingsToast" role="status" aria-live="polite" hidden>
        <i data-lucide="check-circle" class="lucide-icon lucide-icon-sm" aria-hidden="true"></i>
        <span id="settingsToastMessage"></span>
    </div>

    {{-- 1. Personal Information --}}
    <section class="dashboard-section settings-section fade-in-element">
        <h2 class="section-title">
            <i data-lucide="user" class="lucide-icon lucide-icon-md" aria-hidden="true"></i>
            Personal Information
        </h2>
        <div class="settings-card">
            <form id="profileForm" class="settings-form" novalidate>
                @csrf
                <div class="settings-form-row">
                    <label for="name" class="settings-label">
                        <i data-lucide="user" class="lucide-icon lucide-icon-sm" aria-hidden="true"></i>
                        Full Name <span class="settings-required">*</span>
                    </label>
                    <input type="text" id="name" name="name" class="settings-input" value="{{ old('name', $user->name) }}" required maxlength="255" autocomplete="name" aria-required="true">
                    <span class="settings-error" id="nameError" role="alert" hidden></span>
                </div>
                <div class="settings-form-row">
                    <label for="email" class="settings-label">
                        <i data-lucide="mail" class="lucide-icon lucide-icon-sm" aria-hidden="true"></i>
                        Email Address <span class="settings-required">*</span>
                    </label>
                    <input type="email" id="email" name="email" class="settings-input" value="{{ old('email', $user->email) }}" required maxlength="255" autocomplete="email" aria-required="true">
                    <span class="settings-error" id="emailError" role="alert" hidden></span>
                </div>
                <div class="settings-form-row">
                    <label class="settings-label">Account Created</label>
                    <div class="settings-readonly">{{ $user->created_at->format('F j, Y') }}</div>
                </div>
                <div class="settings-form-actions">
                    <button type="submit" class="settings-btn settings-btn-primary" id="profileSubmitBtn">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </section>

    {{-- 2. Change Password --}}
    <section class="dashboard-section settings-section fade-in-element">
        <h2 class="section-title">
            <i data-lucide="lock" class="lucide-icon lucide-icon-md" aria-hidden="true"></i>
            Change Password
        </h2>
        <div class="settings-card">
            <form id="passwordForm" class="settings-form" novalidate>
                @csrf
                <div class="settings-form-row">
                    <label for="current_password" class="settings-label">Current Password <span class="settings-required">*</span></label>
                    <input type="password" id="current_password" name="current_password" class="settings-input" autocomplete="current-password" required aria-required="true">
                    <span class="settings-error" id="currentPasswordError" role="alert" hidden></span>
                </div>
                <div class="settings-form-row">
                    <label for="new_password" class="settings-label">New Password <span class="settings-required">*</span></label>
                    <input type="password" id="new_password" name="new_password" class="settings-input" autocomplete="new-password" required minlength="8" aria-required="true">
                    <span class="settings-hint">Minimum 8 characters.</span>
                    <span class="settings-error" id="newPasswordError" role="alert" hidden></span>
                </div>
                <div class="settings-form-row">
                    <label for="new_password_confirmation" class="settings-label">Confirm New Password <span class="settings-required">*</span></label>
                    <input type="password" id="new_password_confirmation" name="new_password_confirmation" class="settings-input" autocomplete="new-password" required minlength="8" aria-required="true">
                    <span class="settings-error" id="newPasswordConfirmationError" role="alert" hidden></span>
                </div>
                <div class="settings-form-actions">
                    <button type="submit" class="settings-btn settings-btn-primary" id="passwordSubmitBtn">
                        Change Password
                    </button>
                </div>
            </form>
        </div>
    </section>
</div>
@endsection
