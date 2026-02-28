<?php

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
        return redirect()->route('dashboard');
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
Route::get('/forgot-password', [AuthController::class, 'showForgotPasswordForm'])->name('password.request');
Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->name('password.email');
