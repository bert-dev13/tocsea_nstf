<?php

use App\Http\Controllers\Admin\AdminAnalyticsController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminSettingsController;
use App\Http\Controllers\Admin\CalculationController;
use App\Http\Controllers\Admin\ModelManagementController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\AskTocseaController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CalculationHistoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\UserSettingsController;
use App\Http\Controllers\ModelBuilderController;
use App\Http\Controllers\SavedEquationController;
use App\Http\Controllers\SoilCalculatorController;
use App\Http\Controllers\WeatherController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (auth()->check()) {
        return auth()->user()->isAdmin()
            ? redirect()->route('admin.dashboard')
            : redirect()->route('dashboard');
    }
    return view('landing');
});

// Auth routes
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::get('/register', [AuthController::class, 'showRegisterForm'])->name('register');
Route::post('/register', [AuthController::class, 'register']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// GET /logout fallback: redirect to home (handles direct navigation, bookmarks, etc.)
Route::get('/logout', function () {
    return redirect('/');
})->name('logout.get');

// Authenticated routes
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/soil-calculator', [SoilCalculatorController::class, 'index'])->name('soil-calculator');
    Route::get('/model-builder', [ModelBuilderController::class, 'index'])->name('model-builder');
    Route::get('/model-builder/saved-equations', [ModelBuilderController::class, 'savedEquations'])->name('model-builder.saved-equations');
    Route::post('/model-builder/run-regression', [ModelBuilderController::class, 'runRegression'])->name('model-builder.run-regression');
    Route::get('/api/weather', [WeatherController::class, 'index'])->name('weather');
    Route::get('/saved-equations', [SavedEquationController::class, 'index'])->name('saved-equations.index');
    Route::post('/saved-equations', [SavedEquationController::class, 'store'])->name('saved-equations.store');
    Route::put('/saved-equations/{saved_equation}', [SavedEquationController::class, 'update'])->name('saved-equations.update');
    Route::delete('/saved-equations/{saved_equation}', [SavedEquationController::class, 'destroy'])->name('saved-equations.destroy');

    Route::get('/calculation-history', [CalculationHistoryController::class, 'index'])->name('calculation-history.index');
    Route::get('/calculation-history/export', [CalculationHistoryController::class, 'export'])->name('calculation-history.export');
    Route::get('/calculation-history/export/pdf', [CalculationHistoryController::class, 'exportPdf'])->name('calculation-history.export-pdf');
    Route::get('/calculation-history/{calculation_history}', [CalculationHistoryController::class, 'show'])->name('calculation-history.show');
    Route::post('/api/calculation-history', [CalculationHistoryController::class, 'store'])->name('calculation-history.store');
    Route::delete('/calculation-history/{calculation_history}', [CalculationHistoryController::class, 'destroy'])->name('calculation-history.destroy');
    Route::get('/calculation-history/{calculation_history}/rerun', [CalculationHistoryController::class, 'rerun'])->name('calculation-history.rerun');

    Route::get('/settings', [UserSettingsController::class, 'index'])->name('settings.index');
    Route::put('/settings/profile', [UserSettingsController::class, 'updateProfile'])->name('settings.profile.update');
    Route::put('/settings/password', [UserSettingsController::class, 'changePassword'])->name('settings.password.update');

    Route::get('/ask-tocsea', [AskTocseaController::class, 'index'])->name('ask-tocsea');
    Route::post('/ask-tocsea/with-context', [AskTocseaController::class, 'withContext'])->name('ask-tocsea.with-context');
    Route::post('/api/ask-tocsea', [AskTocseaController::class, 'send'])
        ->middleware('throttle:10,1')
        ->name('ask-tocsea.send');

    Route::post('/api/tree-recommendations', [SoilCalculatorController::class, 'generateTreeRecommendations'])
        ->middleware('throttle:10,1')
        ->name('tree-recommendations.generate');
});

// Admin API routes (JSON responses for paginated list data)
Route::middleware(['auth', 'admin'])->prefix('api/admin')->group(function () {
    Route::get('/users', [UserManagementController::class, 'apiIndex'])->name('api.admin.users');
    Route::get('/calculations', [CalculationController::class, 'apiIndex'])->name('api.admin.calculations');
    Route::get('/models', [ModelManagementController::class, 'apiIndex'])->name('api.admin.models');
});

// Admin routes (requires admin privileges)
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
    Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
    Route::get('/users/export/excel', [UserManagementController::class, 'exportExcel'])->name('users.export-excel');
    Route::post('/users', [UserManagementController::class, 'store'])->name('users.store');
    Route::get('/users/{user}', [UserManagementController::class, 'show'])->name('users.show');
    Route::put('/users/{user}', [UserManagementController::class, 'update'])->name('users.update');
    Route::delete('/users/{user}', [UserManagementController::class, 'destroy'])->name('users.destroy');
    Route::get('/settings', [AdminSettingsController::class, 'index'])->name('settings.index');
    Route::put('/settings/profile', [AdminSettingsController::class, 'updateProfile'])->name('settings.profile.update');
    Route::put('/settings/password', [AdminSettingsController::class, 'changePassword'])->name('settings.password.update');
    Route::get('/models', [ModelManagementController::class, 'index'])->name('models.index');
    Route::get('/models/export/excel', [ModelManagementController::class, 'exportExcel'])->name('models.export-excel');
    Route::get('/models/{saved_equation}', [ModelManagementController::class, 'show'])->name('models.show');
    Route::delete('/models/{saved_equation}', [ModelManagementController::class, 'destroy'])->name('models.destroy');
    Route::get('/analytics', [AdminAnalyticsController::class, 'index'])->name('analytics.index');
    Route::get('/calculations', [CalculationController::class, 'index'])->name('calculations.index');
    Route::get('/calculations/export/excel', [CalculationController::class, 'exportExcel'])->name('calculations.export-excel');
    Route::get('/calculations/{calculation_history}/pdf', [CalculationController::class, 'exportPdf'])->name('calculations.export-pdf');
    Route::get('/calculations/{calculation_history}', [CalculationController::class, 'show'])->name('calculations.show');
    Route::delete('/calculations/{calculation_history}', [CalculationController::class, 'destroy'])->name('calculations.destroy');
});
Route::get('/forgot-password', [AuthController::class, 'showForgotPasswordForm'])->name('password.request');
Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->name('password.email');
