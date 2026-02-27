@extends('layouts.auth')

@section('title', 'Login')

@section('nav-actions')
    <a href="{{ url('/') }}" class="top-nav-btn top-nav-btn-login"><i data-feather="home" aria-hidden="true"></i><span>Home</span></a>
    <a href="{{ url('/register') }}" class="top-nav-btn top-nav-btn-register"><i data-feather="user-plus" aria-hidden="true"></i><span>Register</span></a>
@endsection

@section('content')
    <section class="auth-section">
        <div class="auth-bg"></div>
        <div class="auth-container">
            <div class="auth-card fade-in-auth">
                <div class="auth-card-header">
                    <div class="auth-icon-wrap" aria-hidden="true"><i data-feather="log-in"></i></div>
                    <h1 class="auth-title">Welcome Back</h1>
                    <p class="auth-subtitle">Sign in to access the TOCSEA research platform</p>
                </div>

                @if (session('status'))
                    <div class="auth-alert auth-alert-success" role="alert">
                        {{ session('status') }}
                    </div>
                @endif

                @if ($errors->any())
                    <div class="auth-alert auth-alert-error" role="alert">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form method="POST" action="{{ url('/login') }}" class="auth-form" id="loginForm" novalidate>
                    @csrf
                    <div class="form-group form-group-icon">
                        <label for="email">Email Address</label>
                        <div class="input-wrap">
                            <span class="input-icon" aria-hidden="true"><i data-feather="mail"></i></span>
                            <input type="email" id="email" name="email" value="{{ old('email') }}" required autocomplete="email" autofocus
                                   placeholder="you@example.com"
                                   class="@error('email') is-invalid @enderror"
                                   aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}"
                                   aria-describedby="email-error">
                        </div>
                        @error('email')
                            <span class="form-error" id="email-error" role="alert">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group form-group-icon form-group-password">
                        <label for="password">Password</label>
                        <div class="input-wrap input-wrap-password">
                            <span class="input-icon" aria-hidden="true"><i data-feather="lock"></i></span>
                            <input type="password" id="password" name="password" required autocomplete="current-password"
                                   placeholder="Enter your password"
                                   class="@error('password') is-invalid @enderror"
                                   aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}"
                                   aria-describedby="password-error">
                            <button type="button" class="password-toggle-btn" aria-label="Show password" title="Show password"
                                    data-password-toggle aria-pressed="false">
                                <i data-feather="eye" aria-hidden="true"></i>
                            </button>
                        </div>
                        @error('password')
                            <span class="form-error" id="password-error" role="alert">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group form-group-row">
                        <label class="checkbox-label">
                            <input type="checkbox" name="remember">
                            <span>Remember me</span>
                        </label>
                        <a href="{{ url('/forgot-password') }}" class="auth-link">Forgot Password?</a>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block auth-btn">
                        <i data-feather="log-in" aria-hidden="true"></i>
                        <span>Sign In</span>
                    </button>
                </form>

                <p class="auth-switch">
                    Don't have an account? <a href="{{ url('/register') }}" class="auth-link-accent">Create one</a>
                </p>
            </div>
        </div>
    </section>
@endsection
