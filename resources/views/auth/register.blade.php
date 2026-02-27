@extends('layouts.auth')

@section('title', 'Register')

@section('nav-actions')
    <a href="{{ url('/') }}" class="top-nav-btn top-nav-btn-login"><i data-feather="home" aria-hidden="true"></i><span>Home</span></a>
    <a href="{{ url('/login') }}" class="top-nav-btn top-nav-btn-register"><i data-feather="log-in" aria-hidden="true"></i><span>Login</span></a>
@endsection

@section('content')
    <section class="auth-section">
        <div class="auth-bg"></div>
        <div class="auth-container">
            <div class="auth-card fade-in-auth">
                <div class="auth-card-header">
                    <div class="auth-icon-wrap" aria-hidden="true"><i data-feather="user-plus"></i></div>
                    <h1 class="auth-title">Create Account</h1>
                    <p class="auth-subtitle">Join the TOCSEA research platform</p>
                </div>

                @if ($errors->any())
                    <div class="auth-alert auth-alert-error" role="alert">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form method="POST" action="{{ url('/register') }}" class="auth-form" id="registerForm" novalidate>
                    @csrf
                    <div class="form-group form-group-icon">
                        <label for="name">Full Name</label>
                        <div class="input-wrap">
                            <span class="input-icon" aria-hidden="true"><i data-feather="user"></i></span>
                            <input type="text" id="name" name="name" value="{{ old('name') }}" required autocomplete="name" autofocus
                                   placeholder="John Doe"
                                   class="@error('name') is-invalid @enderror"
                                   aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}"
                                   aria-describedby="name-error">
                        </div>
                        @error('name')
                            <span class="form-error" id="name-error" role="alert">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group form-group-icon">
                        <label for="email">Email Address</label>
                        <div class="input-wrap">
                            <span class="input-icon" aria-hidden="true"><i data-feather="mail"></i></span>
                            <input type="email" id="email" name="email" value="{{ old('email') }}" required autocomplete="email"
                                   placeholder="you@example.com"
                                   class="@error('email') is-invalid @enderror"
                                   aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}"
                                   aria-describedby="email-error">
                        </div>
                        @error('email')
                            <span class="form-error" id="email-error" role="alert">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group form-group-icon form-group-select" id="provinceGroup">
                        <label for="province">Province</label>
                        <div class="input-wrap input-wrap-select">
                            <span class="input-icon" aria-hidden="true"><i data-feather="map-pin"></i></span>
                            <select id="province" name="province" required
                                    class="@error('province') is-invalid @enderror"
                                    aria-invalid="{{ $errors->has('province') ? 'true' : 'false' }}"
                                    aria-describedby="province-error"
                                    data-psgc-select="province">
                                <option value="">Select Province</option>
                            </select>
                        </div>
                        @error('province')
                            <span class="form-error" id="province-error" role="alert">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group form-group-icon form-group-select" id="municipalityGroup">
                        <label for="municipality">Municipality/City</label>
                        <div class="input-wrap input-wrap-select">
                            <span class="input-icon" aria-hidden="true"><i data-feather="home"></i></span>
                            <select id="municipality" name="municipality" required
                                    class="@error('municipality') is-invalid @enderror"
                                    aria-invalid="{{ $errors->has('municipality') ? 'true' : 'false' }}"
                                    aria-describedby="municipality-error"
                                    data-psgc-select="municipality"
                                    disabled>
                                <option value="">Select Municipality</option>
                            </select>
                        </div>
                        @error('municipality')
                            <span class="form-error" id="municipality-error" role="alert">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group form-group-icon form-group-select" id="barangayGroup">
                        <label for="barangay">Barangay</label>
                        <div class="input-wrap input-wrap-select">
                            <span class="input-icon" aria-hidden="true"><i data-feather="map"></i></span>
                            <select id="barangay" name="barangay" required
                                    class="@error('barangay') is-invalid @enderror"
                                    aria-invalid="{{ $errors->has('barangay') ? 'true' : 'false' }}"
                                    aria-describedby="barangay-error"
                                    data-psgc-select="barangay"
                                    disabled>
                                <option value="">Select Barangay</option>
                            </select>
                        </div>
                        @error('barangay')
                            <span class="form-error" id="barangay-error" role="alert">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group form-group-icon form-group-password" id="passwordGroup">
                        <label for="password">Password</label>
                        <div class="input-wrap">
                            <span class="input-icon" aria-hidden="true"><i data-feather="lock"></i></span>
                            <input type="password" id="password" name="password" required autocomplete="new-password"
                                   placeholder="Min. 8 characters"
                                   class="@error('password') is-invalid @enderror"
                                   aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}"
                                   aria-describedby="password-error password-strength-text">
                        </div>
                        <div class="password-strength" aria-live="polite" aria-atomic="true" hidden>
                            <div class="password-strength-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                                <span class="password-strength-fill"></span>
                            </div>
                            <span class="password-strength-text" id="password-strength-text">Password strength: </span>
                        </div>
                        @error('password')
                            <span class="form-error" id="password-error" role="alert">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group form-group-icon form-group-password-confirm" id="passwordConfirmGroup">
                        <label for="password_confirmation">Confirm Password</label>
                        <div class="input-wrap input-wrap-confirm">
                            <span class="input-icon" aria-hidden="true"><i data-feather="lock"></i></span>
                            <input type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password"
                                   placeholder="Repeat your password"
                                   aria-describedby="password_confirmation-status password_confirmation-error">
                            <span class="confirm-status" id="password_confirmation-status" aria-live="polite" aria-atomic="true" aria-hidden="true"></span>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block auth-btn">
                        <i data-feather="user-plus" aria-hidden="true"></i>
                        <span>Create Account</span>
                    </button>
                </form>

                <p class="auth-switch">
                    Already have an account? <a href="{{ url('/login') }}" class="auth-link-accent">Sign in</a>
                </p>
            </div>
        </div>
    </section>
@endsection
