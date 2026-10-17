<?php

use App\Clinical\ClinicalRecordLocked;
use App\Http\Middleware\PreventRequestForgery;
use App\Http\Middleware\RequestContext;
use App\Http\Middleware\RequirePermission;
use App\Http\Middleware\ResolveTenant;
use App\Http\Middleware\SecurityHeaders;
use App\Tenancy\CrossTenantViolation;
use App\Tenancy\TenantContextMissing;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::middleware([RequestContext::class, SecurityHeaders::class])->group(base_path('routes/deploy.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(RequestContext::class);
        $middleware->web(append: [SecurityHeaders::class], replace: [
            Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class => PreventRequestForgery::class,
        ]);
        $middleware->alias([
            'tenant' => ResolveTenant::class,
            'permission' => RequirePermission::class,
        ]);
        // A clínica ativa precisa existir ANTES do route model binding ({unit}), senão a busca do model
        // roda sem escopo (o escopo falha fechado, mas a resposta correta para ID de outra clínica é 404).
        $middleware->prependToPriorityList(before: SubstituteBindings::class, prepend: ResolveTenant::class);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));
        // Cookies criptografados; somente o de sessão é necessário.
        $middleware->encryptCookies();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Violação de isolamento: registra como crítico e responde 403 genérico (sem detalhes).
        $exceptions->render(fn (CrossTenantViolation $e) => response()->view('errors.403', ['message' => 'Acesso negado.'], 403));
        $exceptions->render(fn (TenantContextMissing $e) => redirect()->route('context.show'));
        // Última barreira do prontuário: registro finalizado não muda (a tela já evita chegar aqui).
        $exceptions->render(fn (ClinicalRecordLocked $e) => back()->withErrors(['record' => $e->getMessage()]));
        $exceptions->dontFlash(['password', 'password_confirmation', 'current_password']);
    })->create();
