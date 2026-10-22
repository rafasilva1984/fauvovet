<?php

use App\Http\Controllers\AgendaController;
use App\Http\Controllers\AppearanceController;
use App\Http\Controllers\AppointmentTypeController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\Auth\ContextController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ClinicalCaseController;
use App\Http\Controllers\ClinicalSearchController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DemoDataController;
use App\Http\Controllers\DocumentBackgroundController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\DocumentTemplateController;
use App\Http\Controllers\EncounterController;
use App\Http\Controllers\ExamController;
use App\Http\Controllers\ExamTypeController;
use App\Http\Controllers\GuardianController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\MedicationController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\PatientAlertController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\PostalCodeController;
use App\Http\Controllers\PrescriptionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SavedSearchController;
use App\Http\Controllers\SearchSynonymController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\VaccinationController;
use App\Http\Controllers\VaccineController;
use Illuminate\Support\Facades\Route;

// Redireciona pelo nome da rota: Route::redirect('/', '/painel') perde o caminho base (/fauvovet) em subpasta.
Route::get('/', fn () => redirect()->route('dashboard'));

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
            Route::get('/anexos/{attachment}', [AttachmentController::class, 'download'])->name('attachments.download');
            Route::get('/documentos/{document}', [DocumentController::class, 'show'])->name('documents.show');
            Route::get('/documentos/{document}/pdf', [DocumentController::class, 'pdf'])->name('documents.pdf');
        });
        Route::middleware('permission:clinical:write')->group(function () {
            Route::post('/pacientes/{patient}/anexos', [AttachmentController::class, 'store'])->middleware('throttle:attachment-upload')->name('attachments.store');
            Route::post('/anexos/{attachment}/remover', [AttachmentController::class, 'remove'])->name('attachments.remove');
            Route::get('/pacientes/{patient}/documentos/novo', [DocumentController::class, 'create'])->name('documents.create');
            Route::post('/pacientes/{patient}/documentos', [DocumentController::class, 'store'])->name('documents.store');
            Route::post('/documentos/{document}/anular', [DocumentController::class, 'void'])->name('documents.void');
            // Receita estruturada (ADR-022): emitir exige clinical:sign, verificado no controlador/serviço.
            Route::get('/pacientes/{patient}/receitas/nova', [PrescriptionController::class, 'create'])->name('prescriptions.create');
            Route::post('/pacientes/{patient}/receitas', [PrescriptionController::class, 'store'])->name('prescriptions.store');
        });
        Route::middleware('permission:medications:configure')->group(function () {
            Route::get('/configuracoes/medicamentos', [MedicationController::class, 'index'])->name('medications.index');
            Route::get('/configuracoes/medicamentos/novo', [MedicationController::class, 'create'])->name('medications.create');
            Route::post('/configuracoes/medicamentos', [MedicationController::class, 'store'])->name('medications.store');
            Route::get('/configuracoes/medicamentos/{medication}/editar', [MedicationController::class, 'edit'])->name('medications.edit');
            Route::put('/configuracoes/medicamentos/{medication}', [MedicationController::class, 'update'])->name('medications.update');
        });
        Route::middleware('permission:documents:templates')->group(function () {
            Route::get('/configuracoes/modelos-de-documento', [DocumentTemplateController::class, 'index'])->name('document-templates.index');
            Route::get('/configuracoes/modelos-de-documento/novo', [DocumentTemplateController::class, 'create'])->name('document-templates.create');
            Route::post('/configuracoes/modelos-de-documento', [DocumentTemplateController::class, 'store'])->name('document-templates.store');
            Route::get('/configuracoes/modelos-de-documento/importar', [DocumentTemplateController::class, 'importForm'])->name('document-templates.import');
            Route::post('/configuracoes/modelos-de-documento/importar', [DocumentTemplateController::class, 'import'])->middleware('throttle:attachment-upload')->name('document-templates.import.store');
            Route::get('/configuracoes/modelos-de-documento/{template}/original', [DocumentTemplateController::class, 'original'])->name('document-templates.original');
            Route::get('/configuracoes/modelos-de-documento/{template}/editar', [DocumentTemplateController::class, 'edit'])->name('document-templates.edit');
            Route::put('/configuracoes/modelos-de-documento/{template}', [DocumentTemplateController::class, 'update'])->name('document-templates.update');
            Route::get('/configuracoes/fundos-de-documento', [DocumentBackgroundController::class, 'index'])->name('document-backgrounds.index');
            Route::post('/configuracoes/fundos-de-documento', [DocumentBackgroundController::class, 'store'])->middleware('throttle:attachment-upload')->name('document-backgrounds.store');
            Route::put('/configuracoes/fundos-de-documento/{background}', [DocumentBackgroundController::class, 'update'])->name('document-backgrounds.update');
            Route::post('/configuracoes/fundos-de-documento/{background}/remover', [DocumentBackgroundController::class, 'retire'])->name('document-backgrounds.retire');
            Route::get('/configuracoes/fundos-de-documento/{background}/previa', [DocumentBackgroundController::class, 'preview'])->middleware('throttle:attachment-upload')->name('document-backgrounds.preview');
            Route::get('/configuracoes/fundos-de-documento/{background}/original', [DocumentBackgroundController::class, 'original'])->name('document-backgrounds.original');
        });

        // Vacinação: recepção vê carteira/vencimentos (vaccines:read); equipe clínica registra (vaccines:write).
        Route::middleware('permission:vaccines:read')->group(function () {
            Route::get('/vacinas', [VaccinationController::class, 'index'])->name('vaccinations.index');
            Route::get('/pacientes/{patient}/vacinas/carteira', [VaccinationController::class, 'card'])->name('vaccinations.card');
        });
        Route::middleware('permission:vaccines:write')->group(function () {
            Route::post('/pacientes/{patient}/vacinas', [VaccinationController::class, 'store'])->name('vaccinations.store');
            Route::post('/vacinas/aplicacoes/{vaccination}/anular', [VaccinationController::class, 'void'])->name('vaccinations.void');
        });
        Route::middleware('permission:vaccines:configure')->group(function () {
            Route::get('/configuracoes/vacinas', [VaccineController::class, 'index'])->name('vaccines.index');
            Route::get('/configuracoes/vacinas/nova', [VaccineController::class, 'create'])->name('vaccines.create');
            Route::post('/configuracoes/vacinas', [VaccineController::class, 'store'])->name('vaccines.store');
            Route::get('/configuracoes/vacinas/{vaccine}/editar', [VaccineController::class, 'edit'])->name('vaccines.edit');
            Route::put('/configuracoes/vacinas/{vaccine}', [VaccineController::class, 'update'])->name('vaccines.update');
        });

        // Exames (Fase 3, ADR-019): ver = clinical:read; pedir/etapas/resultado = clinical:write; revisar = clinical:sign (no serviço).
        Route::middleware('permission:clinical:read')->group(function () {
            Route::get('/exames', [ExamController::class, 'index'])->name('exams.index');
            Route::get('/exames/{exam}', [ExamController::class, 'show'])->name('exams.show');
        });
        Route::middleware('permission:clinical:write')->group(function () {
            Route::post('/pacientes/{patient}/exames', [ExamController::class, 'store'])->name('exams.store');
            Route::post('/exames/{exam}/situacao', [ExamController::class, 'advance'])->name('exams.advance');
            Route::post('/exames/{exam}/resultado', [ExamController::class, 'result'])->middleware('throttle:attachment-upload')->name('exams.result');
            Route::post('/exames/{exam}/valores', [ExamController::class, 'values'])->name('exams.values');
            Route::post('/exames/{exam}/revisar', [ExamController::class, 'review'])->name('exams.review');
            Route::post('/exames/{exam}/cancelar', [ExamController::class, 'cancel'])->name('exams.cancel');
        });
        Route::middleware('permission:exams:configure')->group(function () {
            Route::get('/configuracoes/exames', [ExamTypeController::class, 'index'])->name('exam-types.index');
            Route::post('/configuracoes/exames', [ExamTypeController::class, 'store'])->name('exam-types.store');
            Route::put('/configuracoes/exames/{type}', [ExamTypeController::class, 'update'])->name('exam-types.update');
            Route::get('/configuracoes/exames/{type}/parametros', [ExamTypeController::class, 'show'])->name('exam-types.show');
            Route::post('/configuracoes/exames/{type}/parametros', [ExamTypeController::class, 'storeAnalyte'])->name('exam-analytes.store');
            Route::put('/configuracoes/exames/parametros/{analyte}', [ExamTypeController::class, 'updateAnalyte'])->name('exam-analytes.update');
            Route::post('/configuracoes/exames/parametros/{analyte}/faixas', [ExamTypeController::class, 'storeRange'])->name('exam-ranges.store');
            Route::post('/configuracoes/exames/faixas/{range}/remover', [ExamTypeController::class, 'destroyRange'])->name('exam-ranges.destroy');
        });

        // Busca clínica em texto livre no prontuário (ADR-017).
        Route::get('/busca', [ClinicalSearchController::class, 'index'])->middleware(['permission:clinical:read', 'throttle:clinical-search'])->name('search.index');
        Route::post('/busca/salvas', [SavedSearchController::class, 'store'])->middleware('permission:clinical:read')->name('search.saved.store');
        Route::delete('/busca/salvas/{search}', [SavedSearchController::class, 'destroy'])->middleware('permission:clinical:read')->name('search.saved.destroy');
        Route::middleware('permission:search:configure')->group(function () {
            Route::get('/configuracoes/sinonimos', [SearchSynonymController::class, 'index'])->name('search.synonyms');
            Route::post('/configuracoes/sinonimos', [SearchSynonymController::class, 'store'])->name('search.synonyms.store');
            Route::put('/configuracoes/sinonimos/{synonym}', [SearchSynonymController::class, 'update'])->name('search.synonyms.update');
            Route::delete('/configuracoes/sinonimos/{synonym}', [SearchSynonymController::class, 'destroy'])->name('search.synonyms.destroy');
        });
        Route::post('/busca/anexos', [ClinicalSearchController::class, 'readAttachments'])->middleware(['permission:clinical:write', 'throttle:clinical-search'])->name('search.attachments');
        Route::post('/busca/reindexar', [ClinicalSearchController::class, 'rebuild'])->middleware(['permission:tenant:update', 'throttle:clinical-search'])->name('search.rebuild');

        // Casos científicos (séries e relatos a partir dos atendimentos): veterinário e titular.
        Route::middleware('permission:research:manage')->group(function () {
            Route::get('/casos', [ClinicalCaseController::class, 'index'])->name('cases.index');
            Route::get('/casos/novo', [ClinicalCaseController::class, 'create'])->name('cases.create');
            Route::post('/casos', [ClinicalCaseController::class, 'store'])->name('cases.store');
            Route::get('/casos/{case}', [ClinicalCaseController::class, 'show'])->name('cases.show');
            Route::put('/casos/{case}', [ClinicalCaseController::class, 'update'])->name('cases.update');
            Route::put('/casos/{case}/pacientes/{entry}', [ClinicalCaseController::class, 'updateEntry'])->name('cases.entries.update');
            Route::post('/casos/{case}/pacientes/{entry}/remover', [ClinicalCaseController::class, 'removeEntry'])->name('cases.entries.remove');
            Route::get('/casos/{case}/exportar.{format}', [ClinicalCaseController::class, 'export'])->whereIn('format', ['csv', 'pdf'])->name('cases.export');
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
        Route::get('/configuracoes', [SettingsController::class, 'index'])->name('settings.index');
        Route::get('/configuracoes/aparencia', [AppearanceController::class, 'edit'])->middleware('permission:theme:update')->name('settings.appearance');
        Route::put('/configuracoes/aparencia', [AppearanceController::class, 'update'])->middleware('permission:theme:update')->name('settings.appearance.update');

        // Dados fictícios de demonstração (FAUVOVET_DEMO_DATA=true; desligado = 404). Só o titular.
        Route::middleware('permission:tenant:update')->group(function () {
            Route::get('/configuracoes/dados-de-demonstracao', [DemoDataController::class, 'show'])->name('settings.demo-data');
            Route::post('/configuracoes/dados-de-demonstracao', [DemoDataController::class, 'load'])->name('settings.demo-data.load');
            Route::post('/configuracoes/dados-de-demonstracao/atualizar', [DemoDataController::class, 'refresh'])->name('settings.demo-data.refresh');
            Route::post('/configuracoes/dados-de-demonstracao/remover', [DemoDataController::class, 'remove'])->name('settings.demo-data.remove');
        });
    });
});
