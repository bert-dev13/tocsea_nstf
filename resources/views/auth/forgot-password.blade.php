@extends('layouts.auth')

@section('title', 'Forgot Password')

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
                    <div class="auth-icon-wrap" aria-hidden="true"><i data-feather="key"></i></div>
                    <h1 class="auth-title">Forgot Password</h1>
                    <p class="auth-subtitle">Enter your email and we'll send you a reset link</p>
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

                <form method="POST" action="{{ url('/forgot-password') }}" class="auth-form" id="forgotPasswordForm" novalidate>
                    @csrf
                    <div class="form-group form-group-icon">
                        <label for="email">Email Address</label>
                        <div class="input-wrap">
                            <span class="input-icon" aria-hidden="true"><i data-feather="mail"></i></span>
                            <input type="email" id="email" name="email" value="{{ old('email') }}" required autocomplete="email" autofocus
                                   placeholder="you@example.com"
                                   class="@error('email') is-invalid @enderror"
                                   aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}">
                        </div>
                        @error('email')
                            <span class="form-error" role="alert">{{ $message }}</span>
                        @enderror
                    </div>

                    <button type="submit" class="btn btn-primary btn-block auth-btn">
                        <i data-feather="send" aria-hidden="true"></i>
                        <span>Send Reset Link</span>
                    </button>
                </form>

                <p class="auth-switch">
                    <a href="{{ url('/login') }}" class="auth-link-accent">Back to Sign In</a>
                </p>
            </div>
        </div>
    </section>
@endsection
