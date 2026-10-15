<?php

use App\Http\Controllers\DeployController;
use App\Http\Middleware\DeployRateLimit;
use Illuminate\Support\Facades\Route;

// Sem sessão/cookies/CSRF/banco: autenticação só pelo token de deploy (ver DeployController); funciona
// mesmo antes da primeira migração.
Route::prefix('_deploy')->middleware(DeployRateLimit::class)->group(function () {
    Route::get('/diagnostico', [DeployController::class, 'diagnostics']);
    Route::post('/migrar', [DeployController::class, 'migrate']);
    Route::post('/otimizar', [DeployController::class, 'optimize']);
});
