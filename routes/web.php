<?php

use App\Http\Controllers\AppearanceController;
use App\Http\Controllers\Auth\ContextController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\UnitController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/painel');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:login')->name('login.store');
    Route::get('/cadastro', [OnboardingController::class, 'show'])->name('register');
    Route::post('/cadastro', [OnboardingController::class, 'store'])->middleware('throttle:signup')->name('register.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/sair', [LoginController::class, 'destroy'])->name('logout');
    Route::get('/contexto', [ContextController::class, 'show'])->name('context.show');
    Route::post('/contexto', [ContextController::class, 'store'])->name('context.store');

    // Tudo abaixo exige clínica ativa (validada a cada requisição) e permissão do papel.
    Route::middleware('tenant')->group(function () {
        Route::get('/painel', DashboardController::class)->middleware('permission:tenant:read')->name('dashboard');
        Route::get('/unidades', [UnitController::class, 'index'])->middleware('permission:units:read')->name('units.index');
        Route::post('/unidades', [UnitController::class, 'store'])->middleware('permission:units:create')->name('units.store');
        Route::get('/unidades/{unit}', [UnitController::class, 'show'])->middleware('permission:units:read')->name('units.show');
        Route::get('/equipe', TeamController::class)->middleware('permission:members:read')->name('team.index');
        Route::get('/configuracoes/aparencia', [AppearanceController::class, 'edit'])->middleware('permission:theme:update')->name('settings.appearance');
        Route::put('/configuracoes/aparencia', [AppearanceController::class, 'update'])->middleware('permission:theme:update')->name('settings.appearance.update');
    });
});
