<?php

use App\Http\Controllers\AgendaController;
use App\Http\Controllers\AppearanceController;
use App\Http\Controllers\AppointmentTypeController;
use App\Http\Controllers\Auth\ContextController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\DocumentTemplateController;
use App\Http\Controllers\EncounterController;
use App\Http\Controllers\GuardianController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\PatientAlertController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\PostalCodeController;
use App\Http\Controllers\ProfileController;
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

// Convite de equipe (link de uso único): funciona com ou sem sessão; limite de tentativas por IP.
Route::middleware('throttle:invitation')->where(['token' => '[A-Za-z0-9_-]{43}'])->group(function () {
    Route::get('/convite/{token}', [InvitationController::class, 'show'])->name('invitations.show');
    Route::post('/convite/{token}', [InvitationController::class, 'accept'])->name('invitations.accept');
});

Route::middleware('auth')->group(function () {
    Route::post('/sair', [LoginController::class, 'destroy'])->name('logout');
    Route::get('/contexto', [ContextController::class, 'show'])->name('context.show');
    Route::post('/contexto', [ContextController::class, 'store'])->name('context.store');
    Route::get('/cep/{cep}', PostalCodeController::class)->where('cep', '[0-9]{8}')->middleware('throttle:cep')->name('cep');

    // Tudo abaixo exige clínica ativa (validada a cada requisição) e permissão do papel.
    Route::middleware('tenant')->group(function () {
        Route::get('/painel', DashboardController::class)->middleware('permission:tenant:read')->name('dashboard');
        Route::get('/unidades', [UnitController::class, 'index'])->middleware('permission:units:read')->name('units.index');
        Route::post('/unidades', [UnitController::class, 'store'])->middleware('permission:units:create')->name('units.store');
        Route::get('/unidades/{unit}', [UnitController::class, 'show'])->middleware('permission:units:read')->name('units.show');
        Route::middleware('permission:patients:read')->group(function () {
            Route::get('/pacientes', [PatientController::class, 'index'])->name('patients.index');
            Route::get('/responsaveis', [GuardianController::class, 'index'])->name('guardians.index');
        });
        Route::middleware('permission:patients:write')->group(function () {
            Route::get('/pacientes/novo', [PatientController::class, 'create'])->name('patients.create');
            Route::post('/pacientes', [PatientController::class, 'store'])->name('patients.store');
            Route::get('/pacientes/{patient}/editar', [PatientController::class, 'edit'])->name('patients.edit');
            Route::put('/pacientes/{patient}', [PatientController::class, 'update'])->name('patients.update');
            Route::post('/pacientes/{patient}/responsaveis', [PatientController::class, 'link'])->name('patients.guardians.link');
            Route::delete('/pacientes/{patient}/responsaveis/{guardian}', [PatientController::class, 'unlink'])->name('patients.guardians.unlink');
            Route::get('/responsaveis/novo', [GuardianController::class, 'create'])->name('guardians.create');
            Route::post('/responsaveis', [GuardianController::class, 'store'])->name('guardians.store');
            Route::get('/responsaveis/{guardian}/editar', [GuardianController::class, 'edit'])->name('guardians.edit');
            Route::put('/responsaveis/{guardian}', [GuardianController::class, 'update'])->name('guardians.update');
        });
        Route::middleware('permission:patients:read')->group(function () {
            Route::get('/pacientes/{patient}', [PatientController::class, 'show'])->name('patients.show');
            Route::get('/responsaveis/{guardian}', [GuardianController::class, 'show'])->name('guardians.show');
        });
        Route::middleware('permission:schedule:read')->group(function () {
            Route::get('/agenda', [AgendaController::class, 'index'])->name('agenda.index');
        });
        Route::middleware('permission:schedule:write')->group(function () {
            Route::get('/agenda/novo', [AgendaController::class, 'create'])->name('agenda.create');
            Route::post('/agenda', [AgendaController::class, 'store'])->name('agenda.store');
            Route::get('/agenda/{appointment}/editar', [AgendaController::class, 'edit'])->name('agenda.edit');
            Route::put('/agenda/{appointment}', [AgendaController::class, 'update'])->name('agenda.update');
            Route::post('/agenda/{appointment}/status', [AgendaController::class, 'transition'])->name('agenda.transition');
        });
        Route::get('/agenda/{appointment}', [AgendaController::class, 'show'])->middleware('permission:schedule:read')->name('agenda.show');
        Route::middleware('permission:schedule:configure')->group(function () {
            Route::get('/configuracoes/tipos-de-atendimento', [AppointmentTypeController::class, 'index'])->name('settings.appointment-types');
            Route::post('/configuracoes/tipos-de-atendimento', [AppointmentTypeController::class, 'store'])->name('settings.appointment-types.store');
            Route::get('/configuracoes/tipos-de-atendimento/{appointmentType}/editar', [AppointmentTypeController::class, 'edit'])->name('settings.appointment-types.edit');
            Route::put('/configuracoes/tipos-de-atendimento/{appointmentType}', [AppointmentTypeController::class, 'update'])->name('settings.appointment-types.update');
        });
        Route::get('/perfil', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::put('/perfil', [ProfileController::class, 'update'])->name('profile.update');

        // Documentos clínicos: ler/baixar = clinical:read; emitir = clinical:write (receita/atestado/encaminhamento
        // exigem clinical:sign, verificado no serviço); modelos = documents:templates.
        Route::middleware('permission:clinical:read')->group(function () {
            Route::get('/documentos/{document}', [DocumentController::class, 'show'])->name('documents.show');
            Route::get('/documentos/{document}/pdf', [DocumentController::class, 'pdf'])->name('documents.pdf');
        });
        Route::middleware('permission:clinical:write')->group(function () {
            Route::get('/pacientes/{patient}/documentos/novo', [DocumentController::class, 'create'])->name('documents.create');
            Route::post('/pacientes/{patient}/documentos', [DocumentController::class, 'store'])->name('documents.store');
            Route::post('/documentos/{document}/anular', [DocumentController::class, 'void'])->name('documents.void');
        });
        Route::middleware('permission:documents:templates')->group(function () {
            Route::get('/configuracoes/modelos-de-documento', [DocumentTemplateController::class, 'index'])->name('document-templates.index');
            Route::get('/configuracoes/modelos-de-documento/novo', [DocumentTemplateController::class, 'create'])->name('document-templates.create');
            Route::post('/configuracoes/modelos-de-documento', [DocumentTemplateController::class, 'store'])->name('document-templates.store');
            Route::get('/configuracoes/modelos-de-documento/{template}/editar', [DocumentTemplateController::class, 'edit'])->name('document-templates.edit');
            Route::put('/configuracoes/modelos-de-documento/{template}', [DocumentTemplateController::class, 'update'])->name('document-templates.update');
        });

        // Prontuário: leitura clínica; escrita clínica; assinatura (finalizar) verificada no serviço (clinical:sign).
        Route::middleware('permission:clinical:read')->group(function () {
            Route::get('/atendimentos/{encounter}', [EncounterController::class, 'show'])->name('encounters.show');
        });
        Route::middleware('permission:clinical:write')->group(function () {
            Route::post('/pacientes/{patient}/atendimentos', [EncounterController::class, 'store'])->name('encounters.store');
            Route::put('/atendimentos/{encounter}', [EncounterController::class, 'update'])->name('encounters.update');
            Route::post('/atendimentos/{encounter}/adendos', [EncounterController::class, 'addendum'])->name('encounters.addendum');
            Route::post('/pacientes/{patient}/alertas', [PatientAlertController::class, 'store'])->name('alerts.store');
            Route::post('/alertas/{alert}/resolver', [PatientAlertController::class, 'resolve'])->name('alerts.resolve');
        });
        Route::get('/equipe', [TeamController::class, 'index'])->middleware('permission:members:read')->name('team.index');
        Route::post('/equipe/convites', [TeamController::class, 'invite'])->middleware(['permission:members:manage', 'throttle:invite-create'])->name('team.invite');
        Route::delete('/equipe/convites/{invitation}', [TeamController::class, 'revoke'])->middleware('permission:members:manage')->name('team.invite.revoke');
        Route::get('/equipe/{membership}', [TeamController::class, 'edit'])->middleware('permission:members:manage')->name('team.edit');
        Route::put('/equipe/{membership}', [TeamController::class, 'update'])->middleware('permission:members:manage')->name('team.update');
        Route::get('/configuracoes/aparencia', [AppearanceController::class, 'edit'])->middleware('permission:theme:update')->name('settings.appearance');
        Route::put('/configuracoes/aparencia', [AppearanceController::class, 'update'])->middleware('permission:theme:update')->name('settings.appearance.update');
    });
});
